<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Tests\Unit\Frontend;

use FourViewture\AiLabeling\Domain\Label;
use FourViewture\AiLabeling\Domain\Labeling as L;
use FourViewture\AiLabeling\Frontend\FrontendContext;
use FourViewture\AiLabeling\Frontend\LabelRenderer;
use FourViewture\AiLabeling\Service\LabelTextResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

final class LabelRendererTest extends TestCase
{
    private LabelRenderer $subject;

    protected function setUp(): void
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('sL')->willReturnCallback(static fn(string $key): string => match (true) {
            str_ends_with($key, 'default.visual.generated') => 'KI-generiert',
            str_ends_with($key, 'default.visual.modified') => 'Mit KI verändert',
            str_ends_with($key, 'default.text.generated') => 'Dieser Text wurde mit Hilfe von KI erzeugt.',
            str_ends_with($key, 'secondLayer.button') => 'Mehr Informationen',
            str_ends_with($key, 'secondLayer.generated') => 'Erklärung <KI>',
            default => '',
        });
        $GLOBALS['LANG'] = $languageService;
        unset($GLOBALS['TYPO3_REQUEST']);
        $this->subject = new LabelRenderer(
            new FrontendContext($this->createStub(LanguageServiceFactory::class)),
            new LabelTextResolver()
        );
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['LANG']);
    }

    #[Test]
    public function rendersComponentWithInlineSvgAndRealText(): void
    {
        $html = $this->subject->render(new Label(Label::GENERATED, 'KI-generiert'));
        self::assertStringStartsWith('<span class="ai-label ai-label--generated" data-ai-label="generated"><svg class="ai-label__icon"', $html);
        self::assertStringContainsString('aria-hidden="true" focusable="false"', $html);
        self::assertStringContainsString('fill="currentColor"', $html);
        self::assertStringContainsString('fill-rule="evenodd"', $html);
        self::assertStringContainsString('<span class="ai-label__text">KI-generiert</span></span>', $html);
        self::assertStringNotContainsString('title=', $html);
        self::assertStringNotContainsString('<button', $html);
        // disc + A (outer + counter) + I = 4 sub paths of the official symbol
        self::assertSame(4, preg_match_all('/M\d/', $html));
    }

    #[Test]
    public function hiddenTextKeepsTextForScreenReaders(): void
    {
        $html = $this->subject->render(new Label(Label::MODIFIED, 'Mit KI verändert'), ['textVisible' => false]);
        self::assertStringContainsString('ai-label--text-hidden', $html);
        self::assertStringContainsString('<span class="ai-label__text ai-label__text--sr-only">Mit KI verändert</span>', $html);
    }

    #[Test]
    public function escapesText(): void
    {
        $html = $this->subject->render(new Label(Label::GENERATED, '<b>"x"</b> & y'));
        self::assertStringContainsString('&lt;b&gt;&quot;x&quot;&lt;/b&gt; &amp; y', $html);
    }

    #[Test]
    public function secondLayerRendersDisclosureButtonAndPopover(): void
    {
        $html = $this->subject->render(new Label(Label::GENERATED, 'KI-generiert'), ['secondLayer' => true]);
        self::assertMatchesRegularExpression('/<button type="button" class="ai-label__more" popovertarget="(ai-label-[0-9a-f]{12})" aria-controls="\1" aria-expanded="false">/', $html);
        self::assertStringContainsString('<span class="ai-label__sr-only">Mehr Informationen</span></button>', $html);
        self::assertMatchesRegularExpression('/<span id="ai-label-[0-9a-f]{12}" class="ai-label__details" popover>Erklärung &lt;KI&gt;<\/span>/', $html);
    }

    #[Test]
    public function overlayNeverGetsAnInteractiveSecondLayer(): void
    {
        $html = $this->subject->render(new Label(Label::GENERATED, 'KI-generiert'), ['secondLayer' => true, 'modifiers' => ['overlay', 'unknown']]);
        self::assertStringContainsString('class="ai-label ai-label--generated ai-label--overlay"', $html);
        self::assertStringNotContainsString('<button', $html);
    }

    #[Test]
    public function labelForRowUsesActiveFlagInvolvementAndCustomText(): void
    {
        self::assertNull($this->subject->labelForRow([L::F_ACTIVE => 0, L::F_INVOLVEMENT => 3], 'tt_content'));

        $label = $this->subject->labelForRow([L::F_ACTIVE => 1, L::F_INVOLVEMENT => 3], 'tt_content');
        self::assertSame(Label::GENERATED, $label?->type);
        self::assertSame('Dieser Text wurde mit Hilfe von KI erzeugt.', $label->text);

        $label = $this->subject->labelForRow([L::F_ACTIVE => 1, L::F_INVOLVEMENT => 2], 'sys_file_metadata', 2);
        self::assertSame(Label::MODIFIED, $label?->type);
        self::assertSame('Mit KI verändert', $label->text);

        $label = $this->subject->labelForRow([L::F_ACTIVE => '1', L::F_INVOLVEMENT => 1, L::F_LABEL_TEXT => ' Eigener Text '], 'tx_news_domain_model_news');
        self::assertSame(Label::GENERATED, $label?->type);
        self::assertSame('Eigener Text', $label->text);
    }
}
