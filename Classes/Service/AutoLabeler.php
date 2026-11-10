<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Service;

use FourViewture\AiLabeling\Detection\AiContentDetector;
use FourViewture\AiLabeling\Domain\DetectionResult;
use FourViewture\AiLabeling\Domain\Labeling;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Resource\File;

/**
 * Analyses a FAL file and writes the AI labeling fields into its (default language) metadata.
 */
final class AutoLabeler implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * FAL file types that may carry provenance metadata: image (2), audio (3), video (4), application (5).
     * Integers instead of AbstractFile::FILETYPE_* (deprecated in v13) / FileType enum (v13+) for 12–14 compatibility.
     */
    public const SUPPORTED_TYPES = [2, 3, 4, 5];

    /** @var array<string, mixed>|null */
    private ?array $configuration = null;

    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly LabelEvaluator $evaluator,
    ) {}

    public function isAutoDetectEnabled(): bool
    {
        return (bool)($this->getConfiguration()['autoDetect'] ?? true);
    }

    /**
     * @param bool $persist false = dry run
     * @param bool $overrideManual true = also overwrite manually maintained decisions
     */
    public function process(File $file, bool $persist = true, bool $overrideManual = false): ?DetectionResult
    {
        if (!in_array($this->getFileType($file), self::SUPPORTED_TYPES, true)) {
            return null;
        }

        try {
            $path = $file->getForLocalProcessing(false);
            $result = $this->createDetector()->detectFile($path);
        } catch (\Throwable $e) {
            $this->logger?->warning('AI detection failed for file {uid}: {message}', [
                'uid' => $file->getUid(),
                'message' => $e->getMessage(),
            ]);
            return null;
        }

        if ($result === null || !$persist) {
            return $result;
        }

        $metaData = $file->getMetaData();
        $current = $metaData->get();
        $manuallyMaintained = (int)($current[Labeling::F_INVOLVEMENT] ?? 0) > 0
            && (int)($current[Labeling::F_DETECTED] ?? 0) === 0;

        $values = [
            Labeling::F_DETECTED => 1,
            Labeling::F_DETECTION => $result->evidenceAsText(),
        ];

        if (!$manuallyMaintained || $overrideManual) {
            $values[Labeling::F_INVOLVEMENT] = $result->involvement;
            if ($result->tool !== null && trim((string)($current[Labeling::F_TOOL] ?? '')) === '') {
                $values[Labeling::F_TOOL] = mb_substr($result->tool, 0, 255);
            }
            if ($this->isAutoLabelEnabled() && $result->involvement >= Labeling::INVOLVEMENT_MODIFIED
                && (int)($current[Labeling::F_MODE] ?? 0) === Labeling::MODE_AUTO
            ) {
                $values[Labeling::F_MODE] = Labeling::MODE_FORCE;
            }
        }

        $assessment = $this->evaluator->evaluate(array_merge($current, $values));
        $values = array_merge($values, $assessment->toFields());

        $metaData->add($values)->save();

        $this->logger?->info('AI content detected in file {uid} ({identifier}): {evidence}', [
            'uid' => $file->getUid(),
            'identifier' => $file->getIdentifier(),
            'evidence' => $result->evidenceAsText(),
        ]);

        return $result;
    }

    private function createDetector(): AiContentDetector
    {
        $configuration = $this->getConfiguration();
        $limitMb = max(1, (int)($configuration['scanLimitMb'] ?? 32));
        $signatures = array_values(array_filter(array_map(
            'trim',
            explode(',', (string)($configuration['additionalSignatures'] ?? ''))
        )));
        return new AiContentDetector($limitMb * 1024 * 1024, $signatures);
    }

    private function isAutoLabelEnabled(): bool
    {
        return (bool)($this->getConfiguration()['autoLabel'] ?? true);
    }

    private function getFileType(File $file): int
    {
        $type = $file->getType();
        // TYPO3 v14 may return an enum (FileType) instead of an int
        if ($type instanceof \BackedEnum) {
            return (int)$type->value;
        }
        return (int)$type;
    }

    /**
     * @return array<string, mixed>
     */
    private function getConfiguration(): array
    {
        if ($this->configuration === null) {
            try {
                $this->configuration = (array)$this->extensionConfiguration->get(Labeling::EXT_KEY);
            } catch (\Throwable) {
                $this->configuration = [];
            }
        }
        return $this->configuration;
    }
}
