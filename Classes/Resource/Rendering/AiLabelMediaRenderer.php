<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Resource\Rendering;

use FourViewture\AiLabeling\Frontend\FileLabelResolver;
use FourViewture\AiLabeling\Frontend\FrontendContext;
use FourViewture\AiLabeling\Frontend\LabelRenderer;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Rendering\FileRendererInterface;
use TYPO3\CMS\Core\Resource\Rendering\RendererRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Decorates the renderer that would otherwise render a labelled file (core VideoTagRenderer,
 * AudioTagRenderer, YouTubeRenderer, VimeoRenderer – or any other registered renderer):
 * outputs the unchanged HTML of that renderer, followed by the label component.
 *
 * - video / online media: class ai-label--overlay -> CSS anchor positioning at the top of the player
 *   (never over the control bar, pointer-events: none), fallback: small label after the player
 * - audio (or media.overlay = 0): visible label after the player (ai-label--media)
 *
 * Registration: RendererRegistry::registerRendererClass() in ext_localconf.php (12.4–14.3, the core
 * renderers are registered the same way with priority 1). Priority 1000 to come first; the delegate
 * is the highest-priority other renderer that can render the file.
 */
final class AiLabelMediaRenderer implements FileRendererInterface
{
    private const PRIORITY = 1000;

    /** Re-entrance guard (a delegate may ask the registry again) */
    private bool $rendering = false;

    public function __construct(
        private readonly FileLabelResolver $fileLabelResolver,
        private readonly LabelRenderer $labelRenderer,
        private readonly FrontendContext $context,
    ) {}

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    public function canRender(FileInterface $file): bool
    {
        if ($this->rendering || $this->context->isBackendRequest()) {
            return false;
        }
        return $this->fileLabelResolver->labelForFile($file) !== null && $this->delegate($file) !== null;
    }

    /**
     * @param int|string $width
     * @param int|string $height
     * @param array<string, mixed> $options
     */
    public function render(FileInterface $file, $width, $height, array $options = []): string
    {
        $delegate = $this->delegate($file);
        if ($delegate === null) {
            return '';
        }
        $this->rendering = true;
        try {
            $html = (string)$delegate->render($file, $width, $height, $options);
        } finally {
            $this->rendering = false;
        }
        $label = $this->fileLabelResolver->labelForFile($file);
        if ($label === null || trim($html) === '') {
            return $html;
        }
        $isAudio = str_starts_with((string)$file->getMimeType(), 'audio/') || FileLabelResolver::fileType($file) === 3;
        $overlay = !$isAudio && $this->context->getSettings()->mediaOverlay();
        return $html . $this->labelRenderer->render($label, ['modifiers' => $overlay ? ['overlay'] : ['media']]);
    }

    private function delegate(FileInterface $file): ?FileRendererInterface
    {
        foreach (GeneralUtility::makeInstance(RendererRegistry::class)->getRendererInstances() as $renderer) {
            if ($renderer instanceof self) {
                continue;
            }
            if ($renderer->canRender($file)) {
                return $renderer;
            }
        }
        return null;
    }
}
