<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Hooks;

use FourViewture\AiLabeling\Service\RecordOverlay;

/**
 * TYPO3 12 only: $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'][IconFactory::class]['overrideIconOverlay'].
 * The hook was replaced by ModifyRecordOverlayIconIdentifierEvent in TYPO3 13 and is not called there.
 */
final class IconOverlayHook
{
    /**
     * @param array<string, mixed> $row
     * @param array<string, bool> $status
     */
    public function postOverlayPriorityLookup(string $table, array $row, array $status, string $iconName): string
    {
        return RecordOverlay::forRecord($table, $row, $iconName);
    }
}
