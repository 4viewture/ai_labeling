<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\EventListener;

use FourViewture\AiLabeling\Frontend\FrontendContext;
use FourViewture\AiLabeling\Frontend\LabelledImageLocator;
use FourViewture\AiLabeling\Html\ImageLabelRewriter;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;

/**
 * Appends the label text to the alt text of labelled images and the label to their <figcaption>,
 * once per generated (cacheable) page.
 *
 * Content API: TYPO3 14 getContent()/setContent(); TYPO3 12.4 and 13.4 only offer
 * getController()->content (TypoScriptFrontendController) – branched via method_exists().
 */
final class LabelImagesInContent
{
    public function __construct(
        private readonly FrontendContext $context,
        private readonly LabelledImageLocator $locator,
        private readonly ImageLabelRewriter $rewriter,
    ) {}

    public function __invoke(AfterCacheableContentIsGeneratedEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->context->getSettings($request)->altText()) {
            return;
        }
        $useContentApi = method_exists($event, 'getContent') && method_exists($event, 'setContent');
        $content = $useContentApi ? $event->getContent() : (string)$event->getController()->content;
        if (stripos($content, '<img') === false) {
            return;
        }
        $rewritten = $this->rewriter->rewrite(
            $content,
            fn(array $paths): array => $this->locator->labelsFor($paths, $request)
        );
        if ($rewritten === $content) {
            return;
        }
        if ($useContentApi) {
            $event->setContent($rewritten);
        } else {
            $event->getController()->content = $rewritten;
        }
    }
}
