<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Domain;

/**
 * Result of analysing a file for AI provenance metadata.
 */
final class DetectionResult
{
    /**
     * @param list<string> $evidence human-readable technical evidence
     */
    public function __construct(
        public readonly int $involvement,
        public readonly ?string $tool,
        public readonly array $evidence,
    ) {}

    public function evidenceAsText(): string
    {
        return implode("\n", $this->evidence);
    }
}
