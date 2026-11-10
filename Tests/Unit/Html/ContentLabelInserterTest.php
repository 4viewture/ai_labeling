<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Tests\Unit\Html;

use FourViewture\AiLabeling\Html\ContentLabelInserter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContentLabelInserterTest extends TestCase
{
    private const LABEL = '<span class="ai-label">L</span>';
    private const LINE = '<span class="ai-label-line"><span class="ai-label">L</span></span>';
    private const PARAGRAPH = '<p class="ai-label-line"><span class="ai-label">L</span></p>';

    private static function fixture(string $name): string
    {
        return (string)file_get_contents(__DIR__ . '/Fixtures/' . $name . '.html');
    }

    private function insert(string $html, string $type = 'generated'): string
    {
        return (new ContentLabelInserter())->insert($html, $type, self::LABEL);
    }

    /**
     * Removes the inserted parts again: the rest of the markup must be byte-identical.
     */
    private static function strip(string $html): string
    {
        return str_replace([self::LINE, self::PARAGRAPH, ' data-ai-label="generated"', ' data-ai-label="modified"'], '', $html);
    }

    public static function fixtures(): \Generator
    {
        // fixture => [expected first tag with attribute, markup directly before the label]
        yield 'fluid_styled_content default (12.4/13.4/14.3)' => ['fsc-default', '<div id="c12" class="frame frame-default frame-type-text frame-layout-0" data-ai-label="generated">', "Blutspende im Mai\n            </h2>"];
        yield 'localized anchor inside frame' => ['fsc-localized', '<div id="c12" class="frame frame-default frame-type-text frame-layout-0" data-ai-label="generated">', "</a>\n            </h2>"];
        yield 'frame_class = none' => ['fsc-frame-none', '<header data-ai-label="generated">', "Erste Hilfe\n            </h2>"];
        yield 'without header' => ['fsc-no-header', '<div id="c12" class="frame frame-default frame-type-text frame-layout-0" data-ai-label="generated">', 'frame-layout-0" data-ai-label="generated">'];
        yield 'frame none without header' => ['fsc-frame-none-no-header', '<p data-ai-label="generated">', '<p data-ai-label="generated">'];
        yield 'bootstrap_package (.frame-header)' => ['bootstrap-package', 'frame-no-backgroundimage" data-ai-label="generated">', '<span>Rettungsdienst</span></h2>'];
        yield 'b13/container' => ['container', '<div id="c20" class="frame frame-default frame-type-b13-2cols frame-layout-0" data-ai-label="generated">', 'frame-layout-0" data-ai-label="generated">'];
        yield 'b13/container with header' => ['container-with-header', 'frame-layout-0" data-ai-label="generated">', '<h2 class="">Unsere Angebote</h2>'];
    }

    #[Test]
    #[DataProvider('fixtures')]
    public function insertsLabelAccordingToRules(string $fixture, string $firstTag, string $before): void
    {
        $html = self::fixture($fixture);
        $result = $this->insert($html);

        self::assertStringContainsString($firstTag, $result);
        self::assertStringContainsString($before . self::LINE, $result);
        self::assertSame(1, substr_count($result, 'data-ai-label='), 'only the first real element gets the attribute');
        self::assertSame(1, substr_count($result, self::LABEL));
        self::assertSame($html, self::strip($result), 'no other markup changes');
        self::assertSame($result, $this->insert($result), 'idempotent');
    }

    #[Test]
    public function labelIsNeverInsideTheHeading(): void
    {
        $result = $this->insert(self::fixture('fsc-default'));
        self::assertMatchesRegularExpression('/<\/h2>' . preg_quote(self::LINE, '/') . '\s*<h3/', $result);
    }

    #[Test]
    public function nestedContainerChildrenAreNotTouched(): void
    {
        $result = $this->insert(self::fixture('container-with-header'));
        self::assertStringContainsString('<div id="c21" class="frame frame-default frame-type-text"><header><h2>Pflege</h2></header>', $result);
    }

    #[Test]
    public function frameNoneContainerGetsLabelAsFirstChildOfRow(): void
    {
        // .ai-label-line uses flex-basis: 100% / grid-column: 1 / -1 -> own row inside flex/grid
        $result = $this->insert(self::fixture('container-frame-none'));
        self::assertStringStartsWith('<a id="c20"></a><div class="row" data-ai-label="generated">' . self::LINE . '<div class="col-md-6">', $result);
    }

    #[Test]
    public function elementsThatCannotHoldTheLabelGetAParagraphBefore(): void
    {
        $result = $this->insert(self::fixture('table-frame-none'));
        self::assertStringStartsWith('<a id="c30"></a>' . self::PARAGRAPH . '<table class="ce-table" data-ai-label="generated">', $result);

        $result = $this->insert('<img src="a.jpg" alt="x"><p>y</p>');
        self::assertSame(self::PARAGRAPH . '<img src="a.jpg" alt="x" data-ai-label="generated"><p>y</p>', $result);
    }

    #[Test]
    public function contentWithoutElementGetsParagraph(): void
    {
        self::assertSame(self::PARAGRAPH . 'Nur Text', $this->insert('Nur Text'));
        self::assertSame("\n  " . self::PARAGRAPH . 'Text <b>b</b>', $this->insert("\n  Text <b>b</b>"));
        self::assertSame('<!--TYPO3SEARCH_begin-->' . self::PARAGRAPH . 'Text', $this->insert('<!--TYPO3SEARCH_begin-->Text'));
    }

    #[Test]
    public function emptyOutputStaysEmpty(): void
    {
        self::assertSame('', $this->insert(''));
        self::assertSame("  \n", $this->insert("  \n"));
    }

    #[Test]
    public function modifiedType(): void
    {
        self::assertStringStartsWith('<div id="c12" class="frame frame-default frame-type-text frame-layout-0" data-ai-label="modified">', $this->insert(self::fixture('fsc-no-header'), 'modified'));
    }

    #[Test]
    public function quotedGreaterThanInAttributes(): void
    {
        $result = $this->insert('<div data-x="a>b" class=\'frame\'><p>t</p></div>');
        self::assertSame('<div data-x="a>b" class=\'frame\' data-ai-label="generated">' . self::LINE . '<p>t</p></div>', $result);
    }

    #[Test]
    public function headerWithoutHeadingFallsBackToFirstChild(): void
    {
        $result = $this->insert('<div class="frame"><header><p class="date">1.1.</p></header><p>t</p></div>');
        self::assertSame('<div class="frame" data-ai-label="generated">' . self::LINE . '<header><p class="date">1.1.</p></header><p>t</p></div>', $result);
    }

    #[Test]
    public function headerNotAtTheStartIsIgnored(): void
    {
        $result = $this->insert('<div class="frame"><p>intro</p><header><h2>x</h2></header></div>');
        self::assertSame('<div class="frame" data-ai-label="generated">' . self::LINE . '<p>intro</p><header><h2>x</h2></header></div>', $result);
    }
}
