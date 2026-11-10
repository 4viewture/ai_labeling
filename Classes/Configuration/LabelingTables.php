<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Configuration;

use FourViewture\AiLabeling\Domain\Labeling;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Decides which TCA tables get the AI labeling fields.
 *
 * Extension configuration:
 *   tables        = *            (all eligible tables) or comma-separated list
 *   excludeTables = tx_foo,bar   additional tables to skip
 *
 * sys_file_metadata is always included (file labeling / auto detection).
 */
final class LabelingTables
{
    /** Never labelled: system, user, permission and configuration tables */
    private const EXCLUDED_PREFIXES = ['be_', 'sys_', 'cache_', 'cf_', 'index_', 'tx_scheduler', 'tx_extensionmanager', 'tx_impexp', 'tx_linkvalidator', 'tx_ailabeling'];
    private const EXCLUDED_TABLES = ['fe_users', 'fe_groups'];
    private const ALWAYS_INCLUDED = ['sys_file_metadata'];

    /**
     * @param array<string, mixed> $tableTca
     */
    public static function isEligible(string $table, array $tableTca): bool
    {
        if (in_array($table, self::ALWAYS_INCLUDED, true)) {
            return true;
        }
        $configuration = self::configuration();
        $excluded = GeneralUtility::trimExplode(',', (string)($configuration['excludeTables'] ?? ''), true);
        if (in_array($table, $excluded, true)) {
            return false;
        }

        $included = trim((string)($configuration['tables'] ?? '*'));
        if ($included !== '' && $included !== '*') {
            return in_array($table, GeneralUtility::trimExplode(',', $included, true), true)
                && self::isLabelableStructure($tableTca);
        }

        if (in_array($table, self::EXCLUDED_TABLES, true)) {
            return false;
        }
        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($table, $prefix)) {
                return false;
            }
        }
        $ctrl = $tableTca['ctrl'] ?? [];
        if (!empty($ctrl['adminOnly']) || !empty($ctrl['readOnly']) || !empty($ctrl['is_static'])
            || (int)($ctrl['rootLevel'] ?? 0) === 1
        ) {
            return false;
        }
        return self::isLabelableStructure($tableTca);
    }

    /**
     * Tables that actually carry the fields (after TCA compilation).
     *
     * @param array<string, mixed>|null $tca defaults to $GLOBALS['TCA']
     * @return list<string>
     */
    public static function labeledTables(?array $tca = null): array
    {
        $tca ??= $GLOBALS['TCA'] ?? [];
        $tables = [];
        foreach ($tca as $table => $tableTca) {
            if (isset($tableTca['columns'][Labeling::F_INVOLVEMENT])) {
                $tables[] = (string)$table;
            }
        }
        return $tables;
    }

    public static function isLabeled(string $table): bool
    {
        return isset($GLOBALS['TCA'][$table]['columns'][Labeling::F_INVOLVEMENT]);
    }

    /**
     * @param array<string, mixed> $tableTca
     */
    private static function isLabelableStructure(array $tableTca): bool
    {
        return is_array($tableTca['types'] ?? null) && $tableTca['types'] !== [] && is_array($tableTca['columns'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private static function configuration(): array
    {
        try {
            return (array)GeneralUtility::makeInstance(ExtensionConfiguration::class)->get(Labeling::EXT_KEY);
        } catch (\Throwable) {
            return [];
        }
    }
}
