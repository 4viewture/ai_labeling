<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Resource\Processing;

use FourViewture\AiLabeling\Frontend\FileLabelResolver;
use FourViewture\AiLabeling\Frontend\FrontendContext;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\OnlineMedia\Event\AfterVideoPreviewFetchedEvent;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\OnlineMediaHelperInterface;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\OnlineMediaHelperRegistry;
use TYPO3\CMS\Core\Resource\Processing\LocalImageProcessor;
use TYPO3\CMS\Core\Resource\Processing\TaskInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Burns the compact EU "AI" symbol into processed images of labelled files (EU AI Act Art. 50,
 * Code of Practice 1.2.1 c: "directly embedded into the content").
 *
 * Registered in $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['processors'] after SvgImageProcessor /
 * DeferredBackendImageProcessor and before OnlineMediaPreviewProcessor / LocalImageProcessor
 * (same keys in 12.4, 13.4, 14.3).
 *
 * - canProcessTask(): Image.Preview / Image.CropScaleMask, not in backend requests (no backend previews;
 *   CLI cache warmers produce the same files as the frontend), images.burnIn, original file labelled
 *   (tx_ailabeling_active = 1), raster source (jpg, png, webp, static gif) or online media
 *   (YouTube/Vimeo preview image), raster target.
 * - processTask(): runs the core local processing (processTaskWithLocalFile(): public in 12/13,
 *   protected in 14 – reachable as subclass in all versions). The "existing target file" shortcut of
 *   LocalImageProcessor::processTask() is skipped on purpose: it could reuse an already labelled file
 *   and burn the symbol in twice. Then the symbol is composited onto the result. If the core would
 *   deliver the original file unprocessed, a derived file is created anyway.
 * - Failure: the core result is kept without label, a warning is logged.
 */
final class AiLabelImageProcessor extends LocalImageProcessor
{
    private const RASTER_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function canProcessTask(TaskInterface $task): bool
    {
        if ($task->getType() !== 'Image' || !in_array($task->getName(), ['Preview', 'CropScaleMask'], true)) {
            return false;
        }
        $context = GeneralUtility::makeInstance(FrontendContext::class);
        if ($context->isBackendRequest() || !$context->getSettings()->burnIn()) {
            return false;
        }
        $source = $task->getSourceFile();
        if (!in_array(self::targetExtension($task), self::RASTER_EXTENSIONS, true)) {
            return false;
        }
        if (GeneralUtility::makeInstance(FileLabelResolver::class)->labelForFile($source) === null) {
            return false;
        }
        if ($this->isOnlineMedia($source)) {
            $preview = $this->previewImageOfOnlineMedia($source);
            return $preview !== '' && is_file($preview);
        }
        $extension = strtolower($source->getExtension());
        if (!in_array($extension, self::RASTER_EXTENSIONS, true)) {
            return false;
        }
        return !in_array($extension, ['gif', 'webp'], true) || !$this->isAnimated($source);
    }

    public function processTask(TaskInterface $task): void
    {
        $source = $task->getSourceFile();
        $preview = $this->isOnlineMedia($source) ? $this->previewImageOfOnlineMedia($source) : null;
        $this->processTaskWithLocalFile($task, $preview);
        if (!$task->isExecuted() || !$task->isSuccessful()) {
            return;
        }

        $processedFile = $task->getTargetFile();
        $usesOriginal = $processedFile->usesOriginalFile();
        try {
            if ($usesOriginal) {
                $localFile = $preview !== null ? $this->copyToTemp($preview) : $source->getForLocalProcessing(true);
            } else {
                $localFile = $processedFile->getForLocalProcessing(true);
            }
        } catch (\Throwable $e) {
            $this->logger?->warning('AI label: no local copy of processed file for {file}: {message}', ['file' => $source->getIdentifier(), 'message' => $e->getMessage()]);
            return;
        }

        $settings = GeneralUtility::makeInstance(FrontendContext::class)->getSettings();
        $compositor = GeneralUtility::makeInstance(ImageLabelCompositor::class);
        $result = $compositor->composite($localFile, $settings->burnInParameters());
        if ($result !== ImageLabelCompositor::RESULT_DONE) {
            if ($result === ImageLabelCompositor::RESULT_FAILED) {
                $this->logger?->warning('AI label could not be burned into processed file of {file}', ['file' => $source->getIdentifier()]);
            }
            @unlink($localFile);
            return;
        }

        try {
            clearstatcache(true, $localFile);
            $dimensions = @getimagesize($localFile) ?: [0, 0];
            if ($usesOriginal) {
                // derived file instead of the unprocessed original
                $processedFile->setName($task->getTargetFileName());
            }
            $processedFile->updateProperties([
                'width' => (int)$dimensions[0],
                'height' => (int)$dimensions[1],
                'size' => (int)filesize($localFile),
                'checksum' => $task->getConfigurationChecksum(),
            ]);
            $processedFile->updateWithLocalFile($localFile);
        } catch (\Throwable $e) {
            $this->logger?->warning('AI label: storing the labelled file of {file} failed: {message}', ['file' => $source->getIdentifier(), 'message' => $e->getMessage()]);
            @unlink($localFile);
        }
    }

    /**
     * TaskInterface::getTargetFileExtension() exists in 13.4+, in 12.4 only in AbstractTask.
     */
    private static function targetExtension(TaskInterface $task): string
    {
        if (method_exists($task, 'getTargetFileExtension')) {
            return strtolower((string)$task->getTargetFileExtension());
        }
        return strtolower(pathinfo((string)$task->getTargetFileName(), PATHINFO_EXTENSION));
    }

    private function isOnlineMedia(File $file): bool
    {
        return GeneralUtility::makeInstance(OnlineMediaHelperRegistry::class)->hasOnlineMediaHelper($file->getExtension());
    }

    /**
     * Same as the core PreviewProcessing (private there): helper preview image + AfterVideoPreviewFetchedEvent.
     */
    private function previewImageOfOnlineMedia(File $file): string
    {
        try {
            $helper = GeneralUtility::makeInstance(OnlineMediaHelperRegistry::class)->getOnlineMediaHelper($file);
            if (!$helper instanceof OnlineMediaHelperInterface) {
                return '';
            }
            $event = new AfterVideoPreviewFetchedEvent($file, $helper, $helper->getPreviewImage($file));
            GeneralUtility::makeInstance(EventDispatcherInterface::class)->dispatch($event);
            return $event->getPreviewImageFilename();
        } catch (\Throwable) {
            return '';
        }
    }

    private function copyToTemp(string $path): string
    {
        $temp = GeneralUtility::tempnam('ai-label-', '.' . pathinfo($path, PATHINFO_EXTENSION));
        if (!@copy($path, $temp)) {
            throw new \RuntimeException('Copy of ' . $path . ' failed', 1762790502);
        }
        return $temp;
    }

    /**
     * Animated GIF (more than one graphic control extension) or animated WebP (ANIM chunk).
     */
    private function isAnimated(File $file): bool
    {
        try {
            $path = $file->getForLocalProcessing(false);
            $size = (int)@filesize($path);
            // very large GIF/WebP: do not read them completely, treat as animated (skip)
            $head = $size > 0 && $size <= 32 * 1024 * 1024 ? (string)@file_get_contents($path) : '';
        } catch (\Throwable) {
            return true;
        }
        if ($head === '') {
            return true;
        }
        if (str_starts_with($head, 'GIF')) {
            return preg_match_all('/\x00\x21\xF9\x04/', $head) > 1;
        }
        return str_starts_with($head, 'RIFF') && str_contains(substr($head, 0, 64), 'ANIM');
    }
}
