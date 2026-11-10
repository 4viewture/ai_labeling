<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Hooks;

use FourViewture\AiLabeling\Configuration\LabelingTables;
use FourViewture\AiLabeling\Domain\Labeling;
use FourViewture\AiLabeling\Service\LabelEvaluator;
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
