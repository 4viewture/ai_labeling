<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Frontend;

use FourViewture\AiLabeling\Html\ContentLabelInserter;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsAllowedCallable;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * tt_content.stdWrap.postUserFunc = FourViewture\AiLabeling\Frontend\ContentElementLabeler->process
 *
 * Labels the rendered HTML of a content element if tx_ailabeling_active = 1 ($cObj->data is already
 * language/workspace overlaid). See ContentLabelInserter for the insertion rules.
 *
 * TYPO3 12.4–14.3 call userFuncs with ($content, $conf, $request) and the request attribute
 * "currentContentObject"; setContentObjectRenderer() is called before as well.
 * TYPO3 14 requires #[AsAllowedCallable] on TypoScript callables (Breaking #108054). In 12/13 the
 * attribute class does not exist and the attribute is never instantiated, so it is harmless there.
 */
final class ContentElementLabeler
{
    private ?ContentObjectRenderer $cObj = null;

    public function __construct(
        private readonly FrontendContext $context,
        private readonly LabelRenderer $labelRenderer,
        private readonly ContentLabelInserter $inserter,
    ) {}

    public function setContentObjectRenderer(ContentObjectRenderer $cObj): void
    {
        $this->cObj = $cObj;
    }

    /**
     * @param array<string, mixed> $conf
     */
    #[AsAllowedCallable]
    public function process(string $content, array $conf = [], ?ServerRequestInterface $request = null): string
    {
        $cObj = $request?->getAttribute('currentContentObject');
        $cObj = $cObj instanceof ContentObjectRenderer ? $cObj : $this->cObj;
        if ($cObj === null || trim($content) === '') {
            return $content;
        }
        $table = (string)$cObj->getCurrentTable();
        if ($table !== '' && $table !== 'tt_content') {
            return $content;
        }
        $request = $this->context->getRequest($request);
        if (!$this->context->getSettings($request)->contentEnabled()) {
            return $content;
        }
        $data = is_array($cObj->data) ? $cObj->data : [];
        $label = $this->labelRenderer->labelForRow($data, 'tt_content', (string)($data['CType'] ?? ''), $request);
        if ($label === null) {
            return $content;
        }
        return $this->inserter->insert(
            $content,
            $label->type,
            $this->labelRenderer->render($label, ['modifiers' => ['content'], 'request' => $request])
        );
    }
}
