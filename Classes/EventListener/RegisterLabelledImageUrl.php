<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\EventListener;

use FourViewture\AiLabeling\Frontend\FrontendContext;
use FourViewture\AiLabeling\Frontend\LabelledImageLocator;
use TYPO3\CMS\Core\Resource\Event\AfterFileProcessingEvent;

/**
 * Remembers URL -> labelled file for the alt text / figcaption rewriting (frontend only).
 * AfterFileProcessingEvent is dispatched for every processFile() call, also if the processed file
 * already existed (verified in FileProcessingService 12.4 / 13.4 / 14.3).
 */
final class RegisterLabelledImageUrl
{
    public function __construct(
        private readonly LabelledImageLocator $locator,
        private readonly FrontendContext $context,
    ) {}

    public function __invoke(AfterFileProcessingEvent $event): void
    {
        if ($this->context->isBackendRequest()) {
            return;
        }
        $processedFile = $event->getProcessedFile();
        try {
            $url = $processedFile->getPublicUrl();
        } catch (\Throwable) {
            $url = null;
        }
        $this->locator->registerFile($event->getFile(), $url);
    }
}
