<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Tests\Unit\Html;

use FourViewture\AiLabeling\Html\ImageLabelRewriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImageLabelRewriterTest extends TestCase
{
    private const LABEL = '<span class="ai-label ai-label--caption" data-ai-label="generated">KI-generiert</span>';

    /**
     * @param list<string> $labelled normalized paths of labelled files
     */
    private function rewrite(string $html, array $labelled, ?array &$requested = null): string
    {
        return (new ImageLabelRewriter())->rewrite($html, function (array $paths) use ($labelled, &$requested): array {
            $requested = $paths;
            $labels = [];
            foreach ($paths as $path) {
                if (in_array($path, $labelled, true)) {
                    $labels[$path] = ['text' => 'KI-generiert', 'html' => self::LABEL];
                }
            }
            return $labels;
        });
    }

    #[Test]
    public function rewritesAltAndFigcaptionOfRealTextmediaMarkup(): void
    {
        $html = (string)file_get_contents(__DIR__ . '/Fixtures/drk-textmedia-figure.html');
        $result = $this->rewrite($html, ['fileadmin/_processed_/6/9/csm_familienbildung-artikel-03_8a34f07a64.jpg'], $requested);

        // multi-line srcset entries are all candidates
        self::assertContains('fileadmin/_processed_/6/9/csm_familienbildung-artikel-03_c373dfa772.jpg', $requested);
        self::assertStringContainsString('alt="Pekip Köln (KI-generiert)"', $result);
        self::assertStringContainsString('title="Pekip Köln"', $result, 'title untouched');
        self::assertStringContainsString("Foto: A. Zelck / DRK\n            " . ' ' . self::LABEL . '</figcaption>', $result);
        // nothing else changed
        self::assertSame($html, str_replace([' (KI-generiert)', ' ' . self::LABEL], '', $result));
        self::assertSame($result, $this->rewrite($result, ['fileadmin/_processed_/6/9/csm_familienbildung-artikel-03_8a34f07a64.jpg']), 'idempotent');
    }

    #[Test]
    public function decorativeAndMissingAltStayUntouched(): void
    {
        $html = '<img src="/fileadmin/a.jpg" alt=""><img src="/fileadmin/a.jpg"><img src="/fileadmin/a.jpg" alt="  ">';
        self::assertSame($html, $this->rewrite($html, ['fileadmin/a.jpg']));
    }

    #[Test]
    public function decorativeImageInFigureStillGetsVisibleCaptionLabel(): void
    {
        $html = '<figure><img src="/fileadmin/a.jpg" alt=""><figcaption>Bild</figcaption></figure>';
        self::assertSame('<figure><img src="/fileadmin/a.jpg" alt=""><figcaption>Bild ' . self::LABEL . '</figcaption></figure>', $this->rewrite($html, ['fileadmin/a.jpg']));
    }

    #[Test]
    public function onlyLabelledImagesAreChanged(): void
    {
        $html = '<p><img src="/fileadmin/a.jpg" alt="A"> <img src="/fileadmin/b.jpg" alt="B"></p>';
        self::assertSame('<p><img src="/fileadmin/a.jpg" alt="A"> <img src="/fileadmin/b.jpg" alt="B (KI-generiert)"></p>', $this->rewrite($html, ['fileadmin/b.jpg']));
    }

    #[Test]
    public function matchesAbsoluteEncodedAndQueryUrlsAndLazyAttributes(): void
    {
        $labelled = ['fileadmin/Bilder und Videos/ä.jpg'];
        self::assertStringContainsString('alt="x (KI-generiert)"', $this->rewrite('<img src="https://www.example.org/fileadmin/Bilder%20und%20Videos/%C3%A4.jpg?1700000000" alt="x">', $labelled));
        self::assertStringContainsString('alt="x (KI-generiert)"', $this->rewrite('<img src="data:image/gif;base64,R0lGOD" data-src="/fileadmin/Bilder und Videos/ä.jpg" alt="x">', $labelled));
        self::assertStringContainsString('alt="x (KI-generiert)"', $this->rewrite('<img src="/x.gif" data-srcset="/fileadmin/Bilder%20und%20Videos/%C3%A4.jpg 2x" alt="x">', $labelled));
    }

    #[Test]
    public function pictureSourcesAreCandidatesForTheImg(): void
    {
        $html = '<picture><source srcset="/fileadmin/_processed_/a.webp 1x, /fileadmin/_processed_/a2.webp 2x" type="image/webp"><img src="/fileadmin/fallback.jpg" alt="Bild"></picture><img src="/fileadmin/other.jpg" alt="Andere">';
        $result = $this->rewrite($html, ['fileadmin/_processed_/a2.webp']);
        self::assertStringContainsString('<img src="/fileadmin/fallback.jpg" alt="Bild (KI-generiert)">', $result);
        self::assertStringContainsString('<img src="/fileadmin/other.jpg" alt="Andere">', $result, 'source of a closed picture does not leak');
    }

    #[Test]
    public function figureWithoutCaptionAndNestedFigures(): void
    {
        self::assertSame(
            '<figure><img src="/fileadmin/a.jpg" alt="A (KI-generiert)"></figure>',
            $this->rewrite('<figure><img src="/fileadmin/a.jpg" alt="A"></figure>', ['fileadmin/a.jpg'])
        );
        // gallery: outer caption must not be used for an image of an inner figure without caption
        $html = '<figure class="gallery"><figure><img src="/fileadmin/a.jpg" alt="A"></figure><figcaption>Galerie</figcaption></figure>';
        self::assertSame(
            '<figure class="gallery"><figure><img src="/fileadmin/a.jpg" alt="A (KI-generiert)"></figure><figcaption>Galerie</figcaption></figure>',
            $this->rewrite($html, ['fileadmin/a.jpg'])
        );
        // one label per figure even with two labelled images
        $html = '<figure><img src="/fileadmin/a.jpg" alt="A"><img src="/fileadmin/b.jpg" alt="B"><figcaption>Zwei</figcaption></figure>';
        self::assertSame(1, substr_count($this->rewrite($html, ['fileadmin/a.jpg', 'fileadmin/b.jpg']), self::LABEL));
    }

    #[Test]
    public function altWithQuotesAndAttributesWithGreaterThan(): void
    {
        $html = '<img data-x="a>b" src="/fileadmin/a.jpg" alt=\'Ein "Bild"\'>';
        self::assertSame('<img data-x="a>b" src="/fileadmin/a.jpg" alt="Ein &quot;Bild&quot; (KI-generiert)">', $this->rewrite($html, ['fileadmin/a.jpg']));
    }

    #[Test]
    public function documentsWithoutImagesAreNotScanned(): void
    {
        $called = false;
        (new ImageLabelRewriter())->rewrite('<p>no images</p>', function () use (&$called): array {
            $called = true;
            return [];
        });
        self::assertFalse($called);
    }
}
