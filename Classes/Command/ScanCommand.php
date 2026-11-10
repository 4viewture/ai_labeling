<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Command;

use FourViewture\AiLabeling\Service\AutoLabeler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Scans existing FAL files for AI provenance metadata.
 *
 *   vendor/bin/typo3 ailabeling:scan --dry-run
 *   vendor/bin/typo3 ailabeling:scan --storage=1 --override-manual
 */
final class ScanCommand extends Command
{
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
            ->setHelp('Detects AI-generated or AI-modified files (IPTC DigitalSourceType, C2PA, EXIF, PNG text chunks) and fills the AI labeling metadata.')
            ->addOption('storage', 's', InputOption::VALUE_REQUIRED, 'Only scan this storage uid')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of files', '0')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report, do not write metadata')
            ->addOption('override-manual', null, InputOption::VALUE_NONE, 'Also overwrite manually maintained AI involvement');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');
        $queryBuilder
            ->select('uid')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->eq('missing', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->in(
                    'type',
                    $queryBuilder->createNamedParameter(AutoLabeler::SUPPORTED_TYPES, Connection::PARAM_INT_ARRAY)
                )
            )
            ->orderBy('uid');
        if ($input->getOption('storage') !== null) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('storage', $queryBuilder->createNamedParameter((int)$input->getOption('storage'), Connection::PARAM_INT))
            );
        }
        $limit = (int)$input->getOption('limit');
        if ($limit > 0) {
            $queryBuilder->setMaxResults($limit);
        }

        $uids = $queryBuilder->executeQuery()->fetchFirstColumn();
        $rows = [];
        $io->progressStart(count($uids));
        foreach ($uids as $uid) {
            $io->progressAdvance();
            try {
                $file = $this->resourceFactory->getFileObject((int)$uid);
            } catch (\Throwable) {
                continue;
            }
            $result = $this->autoLabeler->process($file, !$dryRun, (bool)$input->getOption('override-manual'));
            if ($result !== null) {
                $rows[] = [$file->getUid(), $file->getIdentifier(), $result->involvement, $result->tool ?? '–', implode(' | ', $result->evidence)];
            }
        }
        $io->progressFinish();

        if ($rows === []) {
            $io->success('No AI provenance metadata found.');
            return Command::SUCCESS;
        }
        $io->table(['uid', 'identifier', 'involvement', 'tool', 'evidence'], $rows);
        $io->success(sprintf('%d file(s) with AI provenance metadata%s.', count($rows), $dryRun ? ' (dry run, nothing written)' : ' labelled'));
        return Command::SUCCESS;
    }
}
