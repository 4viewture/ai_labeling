<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Form\Element;

use FourViewture\AiLabeling\Domain\Labeling;
use FourViewture\AiLabeling\Service\LabelTextResolver;
use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * FormEngine "fieldWizard" node attached to tx_ailabeling_involvement (fieldInformation would only allow
 * a few inline tags). Note: wizards of a selectSingle are not rendered when the field is read-only.
 *
 * Renders the editor guide (based on "Leitfaden: KI-generierte Inhalte kennzeichnen",
 * Mittelstand-Digital Zentrum Berlin) and a live assessment, which is computed in the
 * browser by the <ai-labeling-guide> web component while the editor fills in the questions.
 *
 * No constructor is defined on purpose: TYPO3 12 passes data via constructor,
 * TYPO3 13+ via setData() – both handled by AbstractNode.
 */
final class GuideInformation extends AbstractNode
{
    private const SOURCE_URL = 'https://digitalzentrum-berlin.de/leitfaden-ki-generierte-inhalte-kennzeichnen';

    public function render(): array
    {
        $resultArray = $this->initializeResultArray();

        $table = (string)($this->data['tableName'] ?? '');
        $recordType = $this->data['recordTypeValue'] ?? null;
        $row = (array)($this->data['databaseRow'] ?? []);
        $itemName = (string)($this->data['parameterArray']['itemFormElName'] ?? '');
        $prefix = (string)preg_replace('/\[[^\]]+\]$/', '', $itemName);

        $resolver = GeneralUtility::makeInstance(LabelTextResolver::class);
        $labels = [
            'statusTitle' => $this->sL('status.title'),
            'status' => [
                Labeling::STATUS_NONE => $this->sL('status.0'),
                Labeling::STATUS_OPTIONAL => $this->sL('status.1'),
                Labeling::STATUS_RECOMMENDED => $this->sL('status.2'),
                Labeling::STATUS_REQUIRED => $this->sL('status.3'),
            ],
            'reasons' => [],
            'active' => [0 => $this->sL('active.0'), 1 => $this->sL('active.1')],
            'suggestion' => $this->sL('suggestion'),
            'labelType' => [
                'generated' => $this->sL('labeltype.generated'),
                'modified' => $this->sL('labeltype.modified'),
            ],
            'defaultText' => [
                'generated' => $this->lang()->sL($resolver->defaultLabelKey($table, $recordType, 'generated')),
                'modified' => $this->lang()->sL($resolver->defaultLabelKey($table, $recordType, 'modified')),
            ],
        ];
        foreach (['none', 'assisted', 'deepfake', 'syntheticPerson', 'productAltered', 'publicInterestText', 'doubt', 'reviewedText', 'noCriteria', 'forced', 'suppressed', 'suppressedRequired'] as $reason) {
            $labels['reasons'][$reason] = $this->sL('reason.' . $reason);
        }

        $isFile = $table === 'sys_file_metadata';
        $html = [];
        $html[] = '<ai-labeling-guide class="ai-labeling-guide"'
            . ' data-prefix="' . htmlspecialchars($prefix) . '"'
            . ' data-fields="' . htmlspecialchars((string)json_encode(Labeling::DECISION_FIELDS)) . '"'
            . ' data-labels="' . htmlspecialchars((string)json_encode($labels)) . '">';

        $html[] = '<div class="ai-labeling-guide__status" data-role="status" aria-live="polite"></div>';

        if ((int)($row[Labeling::F_DETECTED] ?? 0) === 1) {
            $html[] = '<div class="callout callout-info ai-labeling-guide__detected"><div class="callout-content">'
                . '<div class="callout-title">' . $this->t('detected.title') . '</div>'
                . '<div class="callout-body">' . $this->t('detected.text')
                . '<pre class="ai-labeling-guide__evidence">' . htmlspecialchars((string)($row[Labeling::F_DETECTION] ?? '')) . '</pre>'
                . '</div></div></div>';
        }

        $html[] = '<details class="ai-labeling-guide__details">';
        $html[] = '<summary>' . $this->t('guide.open') . '</summary>';
        $html[] = '<div class="ai-labeling-guide__body">';
        $html[] = '<p><strong>' . $this->t('guide.intro') . '</strong></p>';
        $html[] = '<p class="text-body-secondary">' . $this->t('guide.disclaimer') . '</p>';

        $html[] = $this->section('decision', ['1', '2', '3', '4', '5'], true);
        $html[] = $this->section('types', $isFile ? ['image', 'product', 'testimonial', 'av', 'text'] : ['text', 'image', 'product', 'testimonial', 'av', 'chatbot']);
        $html[] = $this->section('exceptions', ['review', 'noDeepfake', 'technical', 'voluntary']);
        $html[] = $this->section('placement', ['basic', 'image', 'shortVideo', 'longVideo', 'live', 'audio', 'text', 'icons']);
        $html[] = $this->section('examples', ['text', 'audio', 'visual']);
        $html[] = $this->section('responsibility', ['provider', 'company']);
        $html[] = $this->section('copyright', ['text']);

        $html[] = '<p class="text-body-secondary small">' . $this->t('guide.source')
            . ' <a href="' . htmlspecialchars(self::SOURCE_URL) . '" target="_blank" rel="noopener noreferrer">'
            . htmlspecialchars(self::SOURCE_URL) . '</a></p>';
        $html[] = '</div></details>';
        $html[] = '</ai-labeling-guide>';

        $resultArray['html'] = implode("\n", $html);
        $resultArray['stylesheetFiles'][] = 'EXT:ai_labeling/Resources/Public/Css/backend.css';
        $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create('@4viewture/ai-labeling/label-guide.js');

        return $resultArray;
    }

    /**
     * @param list<string> $items
     */
    private function section(string $name, array $items, bool $ordered = false): string
    {
        $tag = $ordered ? 'ol' : 'ul';
        $html = '<h4 class="ai-labeling-guide__heading">' . $this->t('section.' . $name . '.title') . '</h4><' . $tag . '>';
        foreach ($items as $item) {
            $html .= '<li>' . $this->t($name . '.' . $item) . '</li>';
        }
        return $html . '</' . $tag . '>';
    }

    private function t(string $key): string
    {
        return htmlspecialchars($this->sL($key));
    }

    private function sL(string $key): string
    {
        return (string)$this->lang()->sL(Labeling::LLL_GUIDE . $key);
    }

    private function lang(): \TYPO3\CMS\Core\Localization\LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
