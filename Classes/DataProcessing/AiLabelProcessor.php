<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\DataProcessing;

use FourViewture\AiLabeling\Frontend\LabelRenderer;
use FourViewture\AiLabeling\Frontend\RecordLabelResolver;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Adds the label of the current record to a FLUIDTEMPLATE / PAGEVIEW:
 *
 *   dataProcessing.100 = ai-label
 *   dataProcessing.100 {
 *       # optional, defaults: table = current table of the cObj, as = aiLabel
 *       table = tx_foo_domain_model_bar
 *       as = aiLabel
 *   }
 *
 * Result: {aiLabel.active} (bool), {aiLabel.type} (generated|modified|null), {aiLabel.text},
 * {aiLabel.html} (rendered component, use with f:format.raw).
 *
 * Alias "ai-label" via service tag "data.processor" (TYPO3 12.1+, verified in 12.4/13.4/14.3).
 */
final class AiLabelProcessor implements DataProcessorInterface
{
    public function __construct(
        private readonly RecordLabelResolver $resolver,
        private readonly LabelRenderer $renderer,
    ) {}

    /**
     * @param array<string, mixed> $contentObjectConfiguration
     * @param array<string, mixed> $processorConfiguration
     * @param array<string, mixed> $processedData
     * @return array<string, mixed>
     */
    public function process(ContentObjectRenderer $cObj, array $contentObjectConfiguration, array $processorConfiguration, array $processedData): array
    {
        if (isset($processorConfiguration['if.']) && !$cObj->checkIf($processorConfiguration['if.'])) {
            return $processedData;
        }
        $as = (string)$cObj->stdWrapValue('as', $processorConfiguration, 'aiLabel');
        $table = (string)$cObj->stdWrapValue('table', $processorConfiguration, '');
        if ($table === '') {
            $table = (string)$cObj->getCurrentTable();
        }
        $data = is_array($processedData['data'] ?? null) ? $processedData['data'] : (is_array($cObj->data) ? $cObj->data : []);
        $request = $cObj->getRequest();

        $label = $table !== '' ? $this->resolver->resolve($data, $table, null, $request) : null;
        $processedData[$as] = [
            'active' => $label !== null,
            'type' => $label?->type,
            'text' => $label?->text,
            'html' => $label !== null ? $this->renderer->render($label, ['request' => $request]) : '',
        ];
        return $processedData;
    }
}
