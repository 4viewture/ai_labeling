<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Service;

use FourViewture\AiLabeling\Configuration\LabelingTables;
use FourViewture\AiLabeling\Domain\Labeling;

/**
 * Determines the AI overlay for a database record icon.
 *
 * Core status overlays (hidden, start/endtime, access, deleted …) have priority – the AI overlay
 * is only used when the core would render no overlay at all.
 */
final class RecordOverlay
{
    public const LABELED = 'overlay-ai-labeled';
    public const UNLABELED = 'overlay-ai-unlabeled';

    /**
     * @param array<string, mixed> $row
     */
    public static function forRecord(string $table, array $row, string $currentOverlay): string
    {
        if ($currentOverlay !== '' || !LabelingTables::isLabeled($table)) {
            return $currentOverlay;
        }
        // Rows without the fields (e.g. reduced selects) cannot be evaluated – no extra queries here.
        if (!array_key_exists(Labeling::F_ACTIVE, $row) && !array_key_exists(Labeling::F_INVOLVEMENT, $row)) {
            return $currentOverlay;
        }
        if (self::int($row, Labeling::F_ACTIVE) === 1) {
            return self::LABELED;
        }
        if (self::int($row, Labeling::F_INVOLVEMENT) >= Labeling::INVOLVEMENT_MODIFIED) {
            return self::UNLABELED;
        }
        return $currentOverlay;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function int(array $row, string $field): int
    {
        $value = $row[$field] ?? 0;
        if (is_array($value)) {
            $value = reset($value);
        }
        return (int)$value;
    }
}
