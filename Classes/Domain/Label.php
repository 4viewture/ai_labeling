<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Domain;

/**
 * A resolved frontend label: type (generated|modified) and the (localized) text.
 */
final class Label
{
    public const GENERATED = 'generated';
    public const MODIFIED = 'modified';

    /**
     * @param self::GENERATED|self::MODIFIED $type
     */
    public function __construct(
        public readonly string $type,
        public readonly string $text,
    ) {}

    /**
     * Label type of a record: "modified" if tx_ailabeling_involvement = 2, otherwise "generated".
     *
     * @param array<string, mixed> $row
     * @return self::GENERATED|self::MODIFIED
     */
    public static function typeFromRow(array $row): string
    {
        $involvement = $row[Labeling::F_INVOLVEMENT] ?? 0;
        $involvement = (int)(is_array($involvement) ? reset($involvement) : $involvement);
        return $involvement === Labeling::INVOLVEMENT_MODIFIED ? self::MODIFIED : self::GENERATED;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function isActive(array $row): bool
    {
        return (int)($row[Labeling::F_ACTIVE] ?? 0) === 1;
    }
}
