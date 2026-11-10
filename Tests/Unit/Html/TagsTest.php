<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Tests\Unit\Html;

use FourViewture\AiLabeling\Html\Tags;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TagsTest extends TestCase
{
    #[Test]
    public function readsAttributesInAllQuotingStyles(): void
    {
        $tag = '<img src="a.jpg" alt=\'Ein &quot;Bild&quot;\' width=100 data-alt="no" hidden>';
        self::assertSame('a.jpg', Tags::getAttribute($tag, 'src'));
        self::assertSame('Ein "Bild"', Tags::getAttribute($tag, 'alt'));
        self::assertSame('100', Tags::getAttribute($tag, 'width'));
        self::assertSame('', Tags::getAttribute($tag, 'hidden'));
        self::assertNull(Tags::getAttribute($tag, 'title'));
        self::assertSame('', Tags::getAttribute('<img alt="" src="x">', 'alt'));
    }

    #[Test]
    public function setsAttributeWithoutTouchingOthers(): void
    {
        self::assertSame('<img src="a" alt="neu &amp; &quot;x&quot;">', Tags::setAttribute('<img src="a" alt="alt">', 'alt', 'neu & "x"'));
        self::assertSame('<img src="a" alt="b">', Tags::setAttribute('<img src="a">', 'alt', 'b'));
        self::assertSame('<img src="a" alt="$1" />', Tags::setAttribute('<img src="a" />', 'alt', '$1'));
        self::assertSame('<img data-alt="x" src="a" alt="y">', Tags::setAttribute('<img data-alt="x" src="a">', 'alt', 'y'));
    }

    #[Test]
    public function findsMatchingClosingTag(): void
    {
        $html = '<div><div>inner</div><br/></div>tail';
        self::assertSame(['start' => 26, 'end' => 32], Tags::findClosingTag($html, 'div', 5));
    }

    #[Test]
    public function classTokens(): void
    {
        self::assertTrue(Tags::hasClass('<div class="a frame-header b">', 'frame-header'));
        self::assertFalse(Tags::hasClass('<div class="frame-header-x">', 'frame-header'));
    }
}
