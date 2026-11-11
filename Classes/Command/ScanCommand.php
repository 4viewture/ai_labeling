<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Command;

use FourViewture\AiLabeling\Domain\Labeling;
use FourViewture\AiLabeling\Domain\ScanPlan;
use FourViewture\AiLabeling\Service\AutoLabeler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * (Re)scans existing FAL files with the same detection as on upload/replace (AutoLabeler).
 *
 *   vendor/bin/typo3 ailabeling:scan --dry-run                 show what would change
 *   vendor/bin/typo3 ailabeling:scan --folder=1:/user_upload/  one folder (recursive)
 *   vendor/bin/typo3 ailabeling:scan --file=123 --file=1:/a.jpg
 *   vendor/bin/typo3 ailabeling:scan --skip-detected           only files never detected before
 *   vendor/bin/typo3 ailabeling:scan --override-manual         also overwrite editorial decisions
 *
 * Editorial decisions are kept by default (AutoLabeler::isManuallyMaintained()); evidence is
 * updated anyway. Label state changes invalidate processed files and page caches.
 */
final class ScanCommand extends Command
{
    private const INVOLVEMENT = ['none', 'assisted', 'modified', 'generated'];
    private const MODE = ['auto', 'always', 'never'];

    public function __construct(
        private readonly AutoLabeler $autoLabeler,
        private readonly ResourceFactory $resourceFactory,
        private readonly ConnectionPool $connectionPool,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                'Detects AI-generated or AI-modified files (IPTC DigitalSourceType, C2PA, EXIF, PNG text chunks, tool names) '
                . 'with the same methods as on upload and fills the AI labeling metadata. Use --dry-run to see the planned changes.'
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report the planned changes, do not write metadata')
            ->addOption('storage', 's', InputOption::VALUE_REQUIRED, 'Only scan this storage uid')
            ->addOption('folder', 'f', InputOption::VALUE_REQUIRED, 'Only scan this folder recursively (combined identifier, e.g. 1:/user_upload/)')
            ->addOption('file', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only scan these files (uid or combined identifier, repeatable)')
            ->addOption('skip-detected', null, InputOption::VALUE_NONE, 'Skip files that were already detected as AI content')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of files', '0')
            ->addOption('override-manual', null, InputOption::VALUE_NONE, 'Also overwrite manually maintained AI involvement');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');
        $overrideManual = (bool)$input->getOption('override-manual');

        try {
            $uids = $this->fileUids($input);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return Command::INVALID;
        }
        if ($uids === []) {
            $io->success('No files to scan.');
            return Command::SUCCESS;
        }

        $stats = ['scanned' => 0, 'found' => 0, 'changes' => 0, 'manual' => 0, 'unchanged' => 0, 'errors' => 0];
        $rows = [];
        $io->progressStart(count($uids));
        foreach ($uids as $uid) {
            $io->progressAdvance();
            try {
                $file = $this->resourceFactory->getFileObject($uid);
            } catch (\Throwable) {
                $stats['errors']++;
                continue;
            }
            if (!$this->autoLabeler->supports($file)) {
                continue;
            }
            $stats['scanned']++;
            $result = $this->autoLabeler->analyse($file);
            if ($result === null) {
                continue;
            }
            $stats['found']++;
            try {
                $plan = $this->autoLabeler->plan($file, $result, $overrideManual);
                if ($plan->manuallyMaintained && !$overrideManual) {
                    $stats['manual']++;
                }
                if (!$plan->hasChanges()) {
                    $stats['unchanged']++;
                    if ($output->isVerbose()) {
                        $rows[] = $this->row($file, $plan, 'unchanged');
                    }
                    continue;
                }
                $stats['changes']++;
                if (!$dryRun) {
                    $this->autoLabeler->apply($file, $plan);
                }
                $rows[] = $this->row($file, $plan, $plan->manuallyMaintained && !$overrideManual ? 'evidence only (manual decision kept)' : '');
            } catch (\Throwable $e) {
                $stats['errors']++;
                $io->warning(sprintf('%s: %s', $file->getCombinedIdentifier(), $e->getMessage()));
            }
        }
        $io->progressFinish();

        if ($rows !== []) {
            $io->table(['uid', 'file', 'involvement', 'mode', 'label active', 'tool', 'evidence', 'note'], $rows);
        }
        $io->definitionList(
            ['files scanned' => $stats['scanned']],
            ['AI provenance found' => $stats['found']],
            [($dryRun ? 'would change' : 'changed') => $stats['changes']],
            ['already up to date' => $stats['unchanged']],
            ['manual decision kept' => $stats['manual']],
            ['errors' => $stats['errors']],
        );
        $dryRun
            ? $io->note('Dry run – nothing was written. Run again without --dry-run to apply the changes.')
            : $io->success(sprintf('%d file(s) updated.', $stats['changes']));
        return $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function fileUids(InputInterface $input): array
    {
        $files = (array)$input->getOption('file');
        if ($files !== []) {
            $uids = [];
            foreach ($files as $file) {
                $file = trim((string)$file);
                if (MathUtility::canBeInterpretedAsInteger($file)) {
                    $uids[] = (int)$file;
                    continue;
                }
                $object = $this->resourceFactory->retrieveFileOrFolderObject($file);
                if (!$object instanceof File) {
                    throw new \InvalidArgumentException('Not a file: ' . $file);
                }
                $uids[] = $object->getUid();
            }
            return array_values(array_unique($uids));
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->select('f.uid')
            ->from('sys_file', 'f')
            ->where(
                $queryBuilder->expr()->eq('f.missing', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->in('f.type', $queryBuilder->createNamedParameter(AutoLabeler::SUPPORTED_TYPES, Connection::PARAM_INT_ARRAY))
            )
            ->orderBy('f.uid');

        $folder = $input->getOption('folder');
        if ($folder !== null && $folder !== '') {
            $object = $this->resourceFactory->retrieveFileOrFolderObject((string)$folder);
            if (!$object instanceof Folder) {
                throw new \InvalidArgumentException('Not a folder: ' . $folder);
            }
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('f.storage', $queryBuilder->createNamedParameter($object->getStorage()->getUid(), Connection::PARAM_INT)),
                $queryBuilder->expr()->like('f.identifier', $queryBuilder->createNamedParameter($queryBuilder->escapeLikeWildcards($object->getIdentifier()) . '%'))
            );
        } elseif ($input->getOption('storage') !== null) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('f.storage', $queryBuilder->createNamedParameter((int)$input->getOption('storage'), Connection::PARAM_INT))
            );
        }

        if ($input->getOption('skip-detected')) {
            $queryBuilder
                ->leftJoin('f', 'sys_file_metadata', 'm', (string)$queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq('m.file', $queryBuilder->quoteIdentifier('f.uid')),
                    $queryBuilder->expr()->eq('m.sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
                ))
                ->andWhere($queryBuilder->expr()->or(
                    $queryBuilder->expr()->isNull('m.' . Labeling::F_DETECTED),
                    $queryBuilder->expr()->eq('m.' . Labeling::F_DETECTED, $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
                ));
        }

        $limit = (int)$input->getOption('limit');
        if ($limit > 0) {
            $queryBuilder->setMaxResults($limit);
        }
        return array_map('intval', $queryBuilder->executeQuery()->fetchFirstColumn());
    }

    /**
     * @return list<string|int>
     */
    private function row(File $file, ScanPlan $plan, string $note): array
    {
        $change = function (string $field, array $names) use ($plan): string {
            $from = (int)($plan->current[$field] ?? 0);
            $to = (int)($plan->values[$field] ?? $from);
            $label = static fn(int $v): string => $names[$v] ?? (string)$v;
            return $from === $to ? $label($from) : $label($from) . ' → ' . $label($to);
        };
        $evidence = $plan->result->evidence;
        return [
            $file->getUid(),
            $file->getCombinedIdentifier(),
            $change(Labeling::F_INVOLVEMENT, self::INVOLVEMENT),
            $change(Labeling::F_MODE, self::MODE),
            $change(Labeling::F_ACTIVE, ['no', 'yes']),
            (string)($plan->values[Labeling::F_TOOL] ?? $plan->current[Labeling::F_TOOL] ?? '') ?: '–',
            mb_strimwidth((string)($evidence[0] ?? ''), 0, 60, '…') . (count($evidence) > 1 ? ' (+' . (count($evidence) - 1) . ')' : ''),
            $note,
        ];
    }
}
