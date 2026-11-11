<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Domain;

/**
 * Result of analysing one file: detection result and the metadata values that would be written
 * (shared by upload detection, the CLI rescan incl. --dry-run and the file list context menu).
 */
final class ScanPlan
{
    /** Fields shown to users as "planned changes" */
    public const REPORTED_FIELDS = [
        Labeling::F_INVOLVEMENT,
        Labeling::F_TOOL,
        Labeling::F_MODE,
        Labeling::F_STATUS,
        Labeling::F_ACTIVE,
        Labeling::F_DETECTED,
    ];

    /**
     * @param array<string, mixed> $current current metadata (default language)
     * @param array<string, int|string> $values values to write
     */
    public function __construct(
        public readonly DetectionResult $result,
        public readonly array $current,
        public readonly array $values,
        public readonly bool $manuallyMaintained,
    ) {}

    /**
     * @return array<string, array{from: string, to: string}> field => change (reported fields only)
     */
    public function changes(): array
    {
        $changes = [];
        foreach (self::REPORTED_FIELDS as $field) {
            if (!array_key_exists($field, $this->values)) {
                continue;
            }
            $from = (string)($this->current[$field] ?? '');
            $to = (string)$this->values[$field];
            if ($from !== $to && !($from === '' && $to === '0')) {
                $changes[$field] = ['from' => $from, 'to' => $to];
            }
        }
        return $changes;
    }

    public function hasChanges(): bool
    {
        foreach ($this->values as $field => $value) {
            if ((string)($this->current[$field] ?? '') !== (string)$value) {
                return true;
            }
        }
        return false;
    }

    /**
     * The label state relevant for the frontend changes (burned-in symbol, page cache).
     */
    public function changesLabelState(): bool
    {
        foreach ([Labeling::F_ACTIVE, Labeling::F_INVOLVEMENT] as $field) {
            if (array_key_exists($field, $this->values) && (int)($this->current[$field] ?? 0) !== (int)$this->values[$field]) {
                return true;
            }
        }
        return false;
    }
}
