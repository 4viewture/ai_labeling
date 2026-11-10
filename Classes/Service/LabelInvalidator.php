<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Called when the label state of a file changes (DataHandler hook for sys_file_metadata, AutoLabeler):
 *
 * 1. deletes all processed files of the original (files + sys_file_processedfile rows, like the core's
 *    ProcessedFileRepository::removeAll() but for one file – also the "uses original file" rows, which
 *    would otherwise keep delivering the unlabelled original),
 * 2. flushes the page cache of all pages that reference the file (sys_file_reference + sys_refindex),
 *    so no cached page points to a deleted processed file.
 *
 * Cache tags: pageId_<pid> of the referencing records (and of the page for references on pages),
 * <table>_<uid> of the referencing record, and tx_news_uid_<uid> / tx_news_pid_<pid> for EXT:news
 * (its list/detail views are cached on other pages than the news storage folder).
 */
final class LabelInvalidator implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const NEWS_TABLE = 'tx_news_domain_model_news';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ResourceFactory $resourceFactory,
        private readonly CacheManager $cacheManager,
    ) {}

    /**
     * @param bool $processedFiles false = label text only changed (alt text / captions): flush pages only
     */
    public function invalidateFile(int $fileUid, bool $processedFiles = true): void
    {
        if ($fileUid <= 0) {
            return;
        }
        if ($processedFiles) {
            $this->deleteProcessedFiles($fileUid);
        }
        $tags = $this->cacheTagsForFile($fileUid);
        if ($tags !== []) {
            $this->cacheManager->flushCachesInGroupByTags('pages', $tags);
        }
    }

    public function deleteProcessedFiles(int $fileUid): void
    {
        try {
            $file = $this->resourceFactory->getFileObject($fileUid);
        } catch (\Throwable) {
            $file = null;
        }
        if ($file !== null) {
            $storage = $file->getStorage();
            $evaluatePermissions = $storage->getEvaluatePermissions();
            $storage->setEvaluatePermissions(false);
            try {
                foreach (GeneralUtility::makeInstance(ProcessedFileRepository::class)->findAllByOriginalFile($file) as $processedFile) {
                    try {
                        $processedFile->getStorage()->setEvaluatePermissions(false);
                        $processedFile->delete(true);
                    } catch (\Throwable $e) {
                        $this->logger?->warning('AI label: processed file {identifier} could not be deleted: {message}', [
                            'identifier' => $processedFile->getIdentifier(),
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            } finally {
                $storage->setEvaluatePermissions($evaluatePermissions);
            }
        }
        $this->connectionPool->getConnectionForTable('sys_file_processedfile')
            ->delete('sys_file_processedfile', ['original' => $fileUid], [Connection::PARAM_INT]);
    }

    /**
     * @return list<string>
     */
    public function cacheTagsForFile(int $fileUid): array
    {
        $tags = [];
        $add = function (string $table, int $uid, int $pid) use (&$tags): void {
            if ($pid > 0) {
                $tags['pageId_' . $pid] = true;
            }
            if ($table === 'pages' && $uid > 0) {
                $tags['pageId_' . $uid] = true;
            }
            if ($table !== '' && $uid > 0) {
                $tags[$table . '_' . $uid] = true;
            }
            if ($table === self::NEWS_TABLE) {
                $tags['tx_news_uid_' . $uid] = true;
                if ($pid > 0) {
                    $tags['tx_news_pid_' . $pid] = true;
                }
            }
        };

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $references = $queryBuilder->select('tablenames', 'uid_foreign', 'pid')
            ->from('sys_file_reference')
            ->where($queryBuilder->expr()->eq('uid_local', $queryBuilder->createNamedParameter($fileUid, Connection::PARAM_INT)))
            ->executeQuery();
        while ($row = $references->fetchAssociative()) {
            $add((string)$row['tablenames'], (int)$row['uid_foreign'], (int)$row['pid']);
        }

        // Direct references (e.g. t3://file links in RTE fields, file fields without references)
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_refindex');
        $queryBuilder->getRestrictions()->removeAll();
        $refindex = $queryBuilder->select('tablename', 'recuid')
            ->from('sys_refindex')
            ->where(
                $queryBuilder->expr()->eq('ref_table', $queryBuilder->createNamedParameter('sys_file')),
                $queryBuilder->expr()->eq('ref_uid', $queryBuilder->createNamedParameter($fileUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->notIn('tablename', $queryBuilder->createNamedParameter(['sys_file_reference', 'sys_file_metadata', 'sys_file'], Connection::PARAM_STR_ARRAY))
            )
            ->executeQuery();
        while ($row = $refindex->fetchAssociative()) {
            $table = (string)$row['tablename'];
            $uid = (int)$row['recuid'];
            $add($table, $uid, $this->pidOf($table, $uid));
        }
        return array_keys($tags);
    }

    private function pidOf(string $table, int $uid): int
    {
        if (!isset($GLOBALS['TCA'][$table]) || $uid <= 0) {
            return 0;
        }
        try {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
            $queryBuilder->getRestrictions()->removeAll();
            return (int)$queryBuilder->select('pid')
                ->from($table)
                ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
                ->executeQuery()
                ->fetchOne();
        } catch (\Throwable) {
            return 0;
        }
    }
}
