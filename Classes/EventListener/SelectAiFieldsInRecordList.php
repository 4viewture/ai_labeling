<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\EventListener;

use FourViewture\AiLabeling\Configuration\LabelingTables;
use FourViewture\AiLabeling\Domain\Labeling;
use TYPO3\CMS\Backend\View\Event\ModifyDatabaseQueryForRecordListingEvent;

/**
 * The list module only selects the displayed columns plus a few system fields. Add the two
 * fields needed for the icon overlay, so the AI marker also shows up there (TYPO3 12.4 – 14).
 * Count queries are not affected (they replace the select part with COUNT(*)).
 */
final class SelectAiFieldsInRecordList
{
    public function __invoke(ModifyDatabaseQueryForRecordListingEvent $event): void
    {
        $table = $event->getTable();
        if (!LabelingTables::isLabeled($table)) {
            return;
        }
        $queryBuilder = $event->getQueryBuilder();
        $queryBuilder->addSelect(
            $queryBuilder->quoteIdentifier($table . '.' . Labeling::F_ACTIVE),
            $queryBuilder->quoteIdentifier($table . '.' . Labeling::F_INVOLVEMENT),
        );
    }
}
