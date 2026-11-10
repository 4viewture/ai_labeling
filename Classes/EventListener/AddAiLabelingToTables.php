<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\EventListener;

use FourViewture\AiLabeling\Configuration\LabelingTables;
use FourViewture\AiLabeling\Configuration\TcaFields;
use TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent;

/**
 * Adds the AI labeling columns, palettes and the "AI" tab to every eligible table and every
 * record type – after the complete TCA of all extensions has been compiled, so tables and types
 * of extensions loaded after this one are covered as well.
 *
 * Database columns: see AddAiLabelingColumnsToDatabase (AlterTableDefinitionStatementsEvent).
 */
final class AddAiLabelingToTables
{
    /** sys_file_metadata types without text questions: image, audio, video */
    private const MEDIA_ONLY_FILE_TYPES = ['2', '3', '4'];

    public function __invoke(AfterTcaCompilationEvent $event): void
    {
        $tca = $event->getTca();
        $columns = TcaFields::columns();
        $palettes = TcaFields::palettes();

        foreach ($tca as $table => $tableTca) {
            $table = (string)$table;
            if (!is_array($tableTca) || !LabelingTables::isEligible($table, $tableTca)) {
                continue;
            }

            foreach ($columns as $field => $definition) {
                $tca[$table]['columns'][$field] ??= $definition;
            }
            $tca[$table]['palettes'] = array_replace($tca[$table]['palettes'] ?? [], $palettes);

            foreach (array_keys($tca[$table]['types']) as $type) {
                $showitem = (string)($tca[$table]['types'][$type]['showitem'] ?? '');
                if (str_contains($showitem, 'ailabeling_involvement')) {
                    continue;
                }
                $withText = !($table === 'sys_file_metadata' && in_array((string)$type, self::MEDIA_ONLY_FILE_TYPES, true));
                $tca[$table]['types'][$type]['showitem'] = ltrim(rtrim($showitem, ", \n\t") . ', ' . TcaFields::tab(true, $withText), ', ');
            }
        }

        $event->setTca($tca);
    }
}
