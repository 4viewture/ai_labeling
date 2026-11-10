<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\EventListener;

use FourViewture\AiLabeling\Service\RecordOverlay;

/**
 * TYPO3 13+: \TYPO3\CMS\Core\Imaging\Event\ModifyRecordOverlayIconIdentifierEvent
 * (page module, list module, FormEngine header, element browser … – everywhere getIconForRecord() is used).
 *
 * TYPO3 12 uses the equivalent hook in \FourViewture\AiLabeling\Hooks\IconOverlayHook.
 */
final class AiRecordIconOverlay
{
    /**
     * @param \TYPO3\CMS\Core\Imaging\Event\ModifyRecordOverlayIconIdentifierEvent $event
     */
    public function __invoke(object $event): void
    {
        $overlay = RecordOverlay::forRecord($event->getTable(), $event->getRow(), $event->getOverlayIconIdentifier());
        if ($overlay !== $event->getOverlayIconIdentifier()) {
            $event->setOverlayIconIdentifier($overlay);
        }
    }
}
