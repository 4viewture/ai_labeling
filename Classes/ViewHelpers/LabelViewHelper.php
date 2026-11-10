<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\ViewHelpers;

use FourViewture\AiLabeling\Frontend\LabelRenderer;
use FourViewture\AiLabeling\Frontend\RecordLabelResolver;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders the AI label of a record, or nothing if the record is not labelled (tx_ailabeling_active = 1).
 *
 *   <html xmlns:ai="http://typo3.org/ns/FourViewture/AiLabeling/ViewHelpers" data-namespace-typo3-fluid="true">
 *   <ai:label record="{newsItem}" />
 *   <ai:label table="tx_foo_domain_model_bar" uid="{uid}" />
 *   <ai:label record="{data}" table="tt_content" />
 *   <ai:label record="{file}" variant="overlay" />   (FAL file reference, e.g. video placeholder)
 *
 * Fluid 2.x (TYPO3 12), 4.x (13) and 5.x (14): instance render() without return type in the
 * parent, initializeArguments() without return type in 2.x – both overridden compatibly.
 */
final class LabelViewHelper extends AbstractViewHelper
{
    use RequestFromRenderingContext;

    /** @var bool */
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('record', 'mixed', 'Extbase model, record array (+ table), Record object (TYPO3 13.4+) or FAL file/file reference');
        $this->registerArgument('table', 'string', 'Table name (required for arrays without Record API)');
        $this->registerArgument('uid', 'int', 'Record uid (with "table", default language uid is overlaid into the frontend language)');
        $this->registerArgument('textVisible', 'bool', 'Override setting label.text (visible|hidden)');
        $this->registerArgument('variant', 'string', '"overlay" (out of flow over the preceding video/iframe/img, pointer-events: none) or "media" (own line below media)');
    }

    public function render(): string
    {
        $request = self::requestFromContext($this->renderingContext);
        $label = GeneralUtility::makeInstance(RecordLabelResolver::class)->resolve(
            $this->arguments['record'] ?? null,
            isset($this->arguments['table']) ? (string)$this->arguments['table'] : null,
            isset($this->arguments['uid']) ? (int)$this->arguments['uid'] : null,
            $request
        );
        if ($label === null) {
            return '';
        }
        $options = ['request' => $request];
        $variant = (string)($this->arguments['variant'] ?? '');
        if (in_array($variant, ['overlay', 'media'], true)) {
            $options['modifiers'] = [$variant];
        }
        if (isset($this->arguments['textVisible'])) {
            $options['textVisible'] = (bool)$this->arguments['textVisible'];
        }
        return GeneralUtility::makeInstance(LabelRenderer::class)->render($label, $options);
    }
}
