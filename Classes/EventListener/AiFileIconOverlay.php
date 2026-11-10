<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\EventListener;

use FourViewture\AiLabeling\Service\FileLabelState;
use TYPO3\CMS\Core\Imaging\Event\ModifyIconForResourcePropertiesEvent;

/**
 * Adds an "AI" overlay to file icons wherever TYPO3 renders them via IconFactory::getIconForResource():
 * file list (list view icon column), element browser, file references, live search (v14) …
 *
 * - overlay-ai-labeled: label active (will be labelled in the frontend)
 * - overlay-ai-unlabeled: AI generated/modified, but currently not labelled (check!)
 *
 * Existing overlays set by the core (e.g. "overlay-missing") are kept.
 * Available in TYPO3 12.4 – 14 (only getSize()/getIconSize() differ, which is not used here).
 */
final class AiFileIconOverlay
{
    public function __construct(private readonly FileLabelState $labelState) {}

    public function __invoke(ModifyIconForResourcePropertiesEvent $event): void
    {
        if ($event->getOverlayIdentifier() !== null) {
            return;
        }
        $state = $this->labelState->forResource($event->getResource());
        if ($state === null) {
            return;
        }
        $event->setOverlayIdentifier($state['active'] ? 'overlay-ai-labeled' : 'overlay-ai-unlabeled');
    }
}
