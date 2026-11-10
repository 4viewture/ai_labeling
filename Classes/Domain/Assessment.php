<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Domain;

/**
 * Result of evaluating a record against the guide (Art. 50 EU AI Act).
 */
final class Assessment
{
    /**
     * @param list<string> $reasons language keys (reason.*)
     */
    public function __construct(
        public readonly int $status,
        public readonly bool $active,
        public readonly array $reasons,
        public readonly ?string $labelType,
    ) {}

    /**
     * Computed values as they are persisted.
     *
     * @return array<string, int|string>
     */
    public function toFields(): array
    {
        return [
            Labeling::F_STATUS => $this->status,
            Labeling::F_ACTIVE => $this->active ? 1 : 0,
            Labeling::F_REASONS => implode(',', $this->reasons),
        ];
    }
}
