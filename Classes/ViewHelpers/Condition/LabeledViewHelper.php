<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\ViewHelpers\Condition;

use FourViewture\AiLabeling\Frontend\RecordLabelResolver;
use FourViewture\AiLabeling\ViewHelpers\RequestFromRenderingContext;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractConditionViewHelper;

/**
 * Condition: is the record labelled (tx_ailabeling_active = 1)?
 *
 *   <ai:condition.labeled record="{newsItem}">
 *       <f:then>…</f:then>
 *       <f:else>…</f:else>
 *   </ai:condition.labeled>
 *
 * verdict() is the evaluation hook of AbstractConditionViewHelper in Fluid 2.x, 4.x and 5.x
 * (a child may add the bool return type, the 2.x parent has none).
 */
final class LabeledViewHelper extends AbstractConditionViewHelper
{
    use RequestFromRenderingContext;

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('record', 'mixed', 'Extbase model, record array (+ table) or Record object (TYPO3 13.4+)');
        $this->registerArgument('table', 'string', 'Table name (required for arrays without Record API)');
        $this->registerArgument('uid', 'int', 'Record uid (together with "table")');
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public static function verdict(array $arguments, RenderingContextInterface $renderingContext): bool
    {
        return GeneralUtility::makeInstance(RecordLabelResolver::class)->resolve(
            $arguments['record'] ?? null,
            isset($arguments['table']) ? (string)$arguments['table'] : null,
            isset($arguments['uid']) ? (int)$arguments['uid'] : null,
            self::requestFromContext($renderingContext),
        ) !== null;
    }
}
