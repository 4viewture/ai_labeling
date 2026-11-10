<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\EventListener;

use FourViewture\AiLabeling\Configuration\LabelingTables;
use FourViewture\AiLabeling\Configuration\TcaFields;
use TYPO3\CMS\Core\Database\Event\AlterTableDefinitionStatementsEvent;

/**
 * Provides the database columns for every table that received the AI labeling TCA columns.
 *
 * sys_file_metadata and tt_content are additionally declared statically in ext_tables.sql, so
 * those two never depend on TCA being loaded during a schema comparison.
 * (TYPO3 13+ would derive most columns from TCA anyway; explicit definitions keep 12.4 working.)
 */
final class AddAiLabelingColumnsToDatabase
{
    private const STATIC_TABLES = ['sys_file_metadata', 'tt_content'];

    public function __invoke(AlterTableDefinitionStatementsEvent $event): void
    {
        $columns = TcaFields::sqlColumns();
        foreach (LabelingTables::labeledTables() as $table) {
            if (in_array($table, self::STATIC_TABLES, true)) {
                continue;
            }
            $event->addSqlData('CREATE TABLE ' . $table . " (\n" . $columns . "\n);");
        }
    }
}
