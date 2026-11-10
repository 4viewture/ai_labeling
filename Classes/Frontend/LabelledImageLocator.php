<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Frontend;

use FourViewture\AiLabeling\Domain\Labeling;
use FourViewture\AiLabeling\Html\ImageLabelRewriter;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;

/**
 * Maps image URLs of a rendered page to labelled files.
 *
 * 1. Request-scoped registry, fed during rendering by AfterFileProcessingEvent (dispatched for new and
 *    for already processed files in 12.4/13.4/14.3, see RegisterLabelledImageUrl).
 * 2. DB fallback for URLs that were not processed in this request (e.g. RTE images, cached
 *    fragments): local storages with relative base path -> sys_file_processedfile / sys_file by
 *    identifier, filtered by tx_ailabeling_active of the default language metadata.
 */
final class LabelledImageLocator
{
    /** @var array<string, int> normalized URL path => original file uid */
    private array $registry = [];

    public function __construct(
        private readonly FileLabelResolver $fileLabelResolver,
        private readonly LabelRenderer $labelRenderer,
        private readonly ConnectionPool $connectionPool,
        private readonly ResourceFactory $resourceFactory,
        private readonly StorageRepository $storageRepository,
    ) {}

    public function register(?string $publicUrl, int $fileUid): void
    {
        $path = $publicUrl !== null ? ImageLabelRewriter::normalizeUrl($publicUrl) : '';
        if ($path !== '' && $fileUid > 0) {
            $this->registry[$path] = $fileUid;
        }
    }

    public function registerFile(FileInterface $file, ?string $processedUrl): void
    {
        $original = FileLabelResolver::originalFile($file);
        if ($original === null || $this->fileLabelResolver->labelForFile($original) === null) {
            return;
        }
        $this->register($processedUrl, $original->getUid());
        $this->register($original->getPublicUrl(), $original->getUid());
    }

    /**
     * @param list<string> $paths normalized URL paths
     * @return array<string, array{text: string, html: string}>
     */
    public function labelsFor(array $paths, ?ServerRequestInterface $request = null): array
    {
        $fileUids = [];
        $unknown = [];
        foreach ($paths as $path) {
            if (isset($this->registry[$path])) {
                $fileUids[$path] = $this->registry[$path];
            } else {
                $unknown[] = $path;
            }
        }
        if ($unknown !== []) {
            $fileUids += $this->lookupInDatabase($unknown);
        }

        $labels = [];
        foreach ($fileUids as $path => $fileUid) {
            try {
                $file = $this->resourceFactory->getFileObject($fileUid);
            } catch (\Throwable) {
                continue;
            }
            $label = $this->fileLabelResolver->labelForFile($file, $request);
            if ($label !== null) {
                $labels[$path] = [
                    'text' => $label->text,
                    'html' => $this->labelRenderer->render($label, ['modifiers' => ['caption'], 'request' => $request]),
                ];
            }
        }
        return $labels;
    }

    /**
     * @param list<string> $paths
     * @return array<string, int>
     */
    private function lookupInDatabase(array $paths): array
    {
        $candidates = [];
        foreach ($this->localStoragePrefixes() as $storageUid => $prefix) {
            foreach ($paths as $path) {
                if (str_starts_with($path, $prefix)) {
                    $candidates[$storageUid]['/' . substr($path, strlen($prefix))] = $path;
                }
            }
        }
        $found = [];
        foreach ($candidates as $storageUid => $identifiers) {
            foreach (array_chunk($identifiers, 200, true) as $chunk) {
                $found += $this->lookupChunk((int)$storageUid, $chunk);
            }
        }
        if ($found === []) {
            return [];
        }
        $active = $this->activeFileUids(array_values(array_unique($found)));
        return array_filter($found, static fn(int $uid): bool => isset($active[$uid]));
    }

    /**
     * @param array<string, string> $identifiers identifier => path
     * @return array<string, int> path => original file uid
     */
    private function lookupChunk(int $storageUid, array $identifiers): array
    {
        $found = [];
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_processedfile');
        $rows = $queryBuilder->select('identifier', 'original')
            ->from('sys_file_processedfile')
            ->where(
                $queryBuilder->expr()->eq('storage', $queryBuilder->createNamedParameter($storageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->in('identifier', $queryBuilder->createNamedParameter(array_keys($identifiers), Connection::PARAM_STR_ARRAY))
            )
            ->executeQuery();
        while ($row = $rows->fetchAssociative()) {
            $found[$identifiers[$row['identifier']] ?? ''] = (int)$row['original'];
        }

        $hashes = [];
        foreach (array_keys($identifiers) as $identifier) {
            $hashes[sha1($identifier)] = $identifier;
            // LocalDriver hashes case-insensitive identifiers lower-cased
            $hashes[sha1(mb_strtolower($identifier))] = $identifier;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');
        $rows = $queryBuilder->select('uid', 'identifier')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('storage', $queryBuilder->createNamedParameter($storageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->in('identifier_hash', $queryBuilder->createNamedParameter(array_keys($hashes), Connection::PARAM_STR_ARRAY))
            )
            ->executeQuery();
        while ($row = $rows->fetchAssociative()) {
            $path = $identifiers[$row['identifier']] ?? null;
            if ($path !== null) {
                $found[$path] = (int)$row['uid'];
            }
        }
        unset($found['']);
        return $found;
    }

    /**
     * @param list<int> $fileUids
     * @return array<int, true>
     */
    private function activeFileUids(array $fileUids): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_metadata');
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder->select('file')
            ->from('sys_file_metadata')
            ->where(
                $queryBuilder->expr()->in('file', $queryBuilder->createNamedParameter($fileUids, Connection::PARAM_INT_ARRAY)),
                $queryBuilder->expr()->eq('sys_language_uid', 0),
                $queryBuilder->expr()->eq(Labeling::F_ACTIVE, 1)
            )
            ->executeQuery();
        $active = [];
        while ($row = $rows->fetchAssociative()) {
            $active[(int)$row['file']] = true;
        }
        return $active;
    }

    /**
     * @return array<int, string> storage uid => public path prefix ("fileadmin/")
     */
    private function localStoragePrefixes(): array
    {
        $prefixes = [];
        foreach ($this->storageRepository->findAll() as $storage) {
            $configuration = $storage->getConfiguration();
            if ($storage->getDriverType() !== 'Local' || ($configuration['pathType'] ?? 'relative') !== 'relative') {
                continue;
            }
            $basePath = trim((string)($configuration['basePath'] ?? ''), '/');
            if ($basePath !== '') {
                $prefixes[$storage->getUid()] = $basePath . '/';
            }
        }
        return $prefixes;
    }
}
