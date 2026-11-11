<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Service;

use FourViewture\AiLabeling\Detection\AiContentDetector;
use FourViewture\AiLabeling\Domain\DetectionResult;
use FourViewture\AiLabeling\Domain\Labeling;
use FourViewture\AiLabeling\Domain\ScanPlan;
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
        private readonly LabelInvalidator $invalidator,
    ) {}

    public function isAutoDetectEnabled(): bool
    {
        return (bool)($this->getConfiguration()['autoDetect'] ?? true);
    }

    /**
     * Upload/replace detection and CLI scan: analyse, plan and (unless dry run) write.
     *
     * @param bool $persist false = dry run
     * @param bool $overrideManual true = also overwrite manually maintained decisions
     */
    public function process(File $file, bool $persist = true, bool $overrideManual = false): ?DetectionResult
    {
        $result = $this->analyse($file);
        if ($result === null || !$persist) {
            return $result;
        }
        $this->apply($file, $this->plan($file, $result, $overrideManual));
        return $result;
    }

    public function supports(File $file): bool
    {
        return in_array($this->getFileType($file), self::SUPPORTED_TYPES, true);
    }

    /**
     * Runs the detector on the file contents (no database changes).
     */
    public function analyse(File $file): ?DetectionResult
    {
        if (!$this->supports($file)) {
            return null;
        }
        try {
            $path = $file->getForLocalProcessing(false);
            return $this->createDetector()->detectFile($path);
        } catch (\Throwable $e) {
            $this->logger?->warning('AI detection failed for file {uid}: {message}', [
                'uid' => $file->getUid(),
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Metadata values the detection result leads to (no database changes) – the basis of dry runs.
     */
    public function plan(File $file, DetectionResult $result, bool $overrideManual = false): ScanPlan
    {
        $current = $file->getMetaData()->get();
        $manuallyMaintained = self::isManuallyMaintained($current, $result);

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
        return new ScanPlan($result, $current, array_merge($values, $assessment->toFields()), $manuallyMaintained);
    }

    /**
     * Writes a plan; invalidates processed files and page caches if the label state changes.
     */
    public function apply(File $file, ScanPlan $plan): void
    {
        if (!$plan->hasChanges()) {
            return;
        }
        $file->getMetaData()->add($plan->values)->save();

        // Label state changed -> processed files (burned-in symbol) and cached pages are outdated
        if ($plan->changesLabelState()) {
            $this->invalidator->invalidateFile($file->getUid());
        }

        $this->logger?->info('AI content detected in file {uid} ({identifier}): {evidence}', [
            'uid' => $file->getUid(),
            'identifier' => $file->getIdentifier(),
            'evidence' => $plan->result->evidenceAsText(),
        ]);
    }

    /**
     * Editorial decisions survive (re)scans:
     * - never detected before: an involvement set by an editor (> 0) is manual
     * - detected before: an involvement that differs from the detection result was changed by an
     *   editor afterwards (incl. "no AI" = 0) – equal values need no change anyway
     *
     * @param array<string, mixed> $current
     */
    public static function isManuallyMaintained(array $current, DetectionResult $result): bool
    {
        $involvement = (int)($current[Labeling::F_INVOLVEMENT] ?? 0);
        if ((int)($current[Labeling::F_DETECTED] ?? 0) === 0) {
            return $involvement > 0;
        }
        return $involvement !== $result->involvement;
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
        // getType(): int in 12.4 (untyped), 13.4 and 14.3
        return (int)$file->getType();
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
