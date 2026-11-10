<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\EventListener;

use FourViewture\AiLabeling\Service\AutoLabeler;
use TYPO3\CMS\Core\Resource\Event\AfterFileAddedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileReplacedEvent;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Labels files automatically if AI provenance metadata is found on upload or replace.
 * Registered via Configuration/Services.yaml (no PHP attribute, to stay compatible with TYPO3 12).
 */
final class FileAiDetectionListener
{
    public function __construct(private readonly AutoLabeler $autoLabeler) {}

    public function onFileAdded(AfterFileAddedEvent $event): void
    {
        $this->handle($event->getFile());
    }

    public function onFileReplaced(AfterFileReplacedEvent $event): void
    {
        $this->handle($event->getFile());
    }

    private function handle(FileInterface $file): void
    {
        if (!$file instanceof File || !$this->autoLabeler->isAutoDetectEnabled()) {
            return;
        }
        $this->autoLabeler->process($file);
    }
}
