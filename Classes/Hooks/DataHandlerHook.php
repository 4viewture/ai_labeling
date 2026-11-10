<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Hooks;

use FourViewture\AiLabeling\Configuration\LabelingTables;
use FourViewture\AiLabeling\Domain\Labeling;
use FourViewture\AiLabeling\Service\LabelEvaluator;
use FourViewture\AiLabeling\Service\LabelInvalidator;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Persists the computed assessment (status, active flag, reasons) so the frontend
 * can later rely on tx_ailabeling_active without re-evaluating.
 */
final class DataHandlerHook
{
    /** @var array<int, bool> file uid => processed files affected (false: label text only) */
    private array $changedFiles = [];

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_postProcessFieldArray(
        string $status,
        string $table,
        string|int $id,
        array &$fieldArray,
        DataHandler $dataHandler
    ): void {
        if (!LabelingTables::isLabeled($table)) {
            return;
        }

        $existing = [];
        if ($status === 'update' && MathUtility::canBeInterpretedAsInteger($id)) {
            $existing = BackendUtility::getRecord($table, (int)$id) ?? [];
        }
        $touched = array_intersect(Labeling::DECISION_FIELDS, array_keys($fieldArray)) !== [];
        if ($status === 'update' && !$touched) {
            if ($table === 'sys_file_metadata') {
                // e.g. only the label text changed
                $this->rememberChangedFile($existing, $fieldArray);
            }
            return;
        }

        $record = array_merge($existing, $fieldArray);
        $assessment = GeneralUtility::makeInstance(LabelEvaluator::class)->evaluate($record);

        foreach ($assessment->toFields() as $field => $value) {
            if ((string)($existing[$field] ?? '') !== (string)$value) {
                $fieldArray[$field] = $value;
            }
        }

        if (in_array('suppressedRequired', $assessment->reasons, true)) {
            $this->addFlashMessage();
        }

        if ($table === 'sys_file_metadata') {
            $this->rememberChangedFile($existing, $fieldArray);
        }
    }

    /**
     * Frontend output depends on active/involvement (burned-in symbol) and the label text (alt text,
     * captions): remember the file, invalidate after all operations (processed files + page cache).
     *
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $fieldArray
     */
    private function rememberChangedFile(array $existing, array $fieldArray): void
    {
        $fileUid = (int)($existing['file'] ?? $fieldArray['file'] ?? 0);
        if ($fileUid <= 0) {
            return;
        }
        $changed = static fn(string $field): bool => array_key_exists($field, $fieldArray)
            && (string)$fieldArray[$field] !== (string)($existing[$field] ?? '');
        $wasActive = (int)($existing[Labeling::F_ACTIVE] ?? 0) === 1;
        $isActive = (int)($fieldArray[Labeling::F_ACTIVE] ?? $existing[Labeling::F_ACTIVE] ?? 0) === 1;
        $image = $changed(Labeling::F_ACTIVE) || (($wasActive || $isActive) && $changed(Labeling::F_INVOLVEMENT));
        $text = ($wasActive || $isActive) && $changed(Labeling::F_LABEL_TEXT);
        if ($image || $text) {
            $this->changedFiles[$fileUid] = ($this->changedFiles[$fileUid] ?? false) || $image;
        }
    }

    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        if ($this->changedFiles === []) {
            return;
        }
        $invalidator = GeneralUtility::makeInstance(LabelInvalidator::class);
        foreach ($this->changedFiles as $fileUid => $processedFiles) {
            $invalidator->invalidateFile($fileUid, $processedFiles);
        }
        $this->changedFiles = [];
    }

    private function addFlashMessage(): void
    {
        $lang = $GLOBALS['LANG'] ?? null;
        if ($lang === null) {
            return;
        }
        $message = GeneralUtility::makeInstance(
            FlashMessage::class,
            $lang->sL(Labeling::LLL_GUIDE . 'flash.suppressedRequired.body'),
            $lang->sL(Labeling::LLL_GUIDE . 'flash.suppressedRequired.title'),
            ContextualFeedbackSeverity::WARNING,
            true
        );
        GeneralUtility::makeInstance(FlashMessageService::class)
            ->getMessageQueueByIdentifier()
            ->addMessage($message);
    }
}
