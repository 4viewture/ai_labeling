<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\EventListener;

use FourViewture\AiLabeling\Domain\Labeling;
use FourViewture\AiLabeling\Service\FileLabelState;
use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * TYPO3 14+ only: \TYPO3\CMS\Filelist\Event\AfterFileListRowPreparedEvent.
 *
 * Appends a text badge ("KI-generiert", "KI-verändert", …) to the name column of the file list
 * (list view) and adds data-ai-labeling attributes to the row. The event does not exist in
 * TYPO3 12/13 – the listener is registered but simply never dispatched there.
 */
final class AiFileListRowBadge
{
    public function __construct(private readonly FileLabelState $labelState) {}

    /**
     * @param \TYPO3\CMS\Filelist\Event\AfterFileListRowPreparedEvent $event
     */
    public function __invoke(object $event): void
    {
        $state = $this->labelState->forResource($event->getResource());
        if ($state === null) {
            return;
        }

        $type = $state['involvement'] === Labeling::INVOLVEMENT_MODIFIED ? 'modified' : 'generated';
        $labelKey = 'filelist.badge.' . $type . ($state['active'] ? '' : '.unlabeled');
        $title = $this->lang()?->sL(Labeling::LLL_GUIDE . $labelKey . '.title') ?? '';
        $label = $this->lang()?->sL(Labeling::LLL_GUIDE . $labelKey) ?: 'AI';

        $data = $event->getData();
        if (isset($data['name'])) {
            $data['name'] .= ' <span class="badge ' . ($state['active'] ? 'badge-danger' : 'badge-warning') . '"'
                . ' title="' . htmlspecialchars($title) . '">' . htmlspecialchars($label) . '</span>';
            $event->setData($data);
        }

        $attributes = $event->getAttributes();
        $attributes['data-ai-labeling'] = $type;
        $attributes['data-ai-labeling-active'] = $state['active'] ? 'true' : 'false';
        $event->setAttributes($attributes);
    }

    private function lang(): ?LanguageService
    {
        return $GLOBALS['LANG'] ?? null;
    }
}
