<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Tests\Unit\Imaging;

use FourViewture\AiLabeling\Imaging\Contrast;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContrastTest extends TestCase
{
    #[Test]
    public function luminanceAndRatioFollowWcag(): void
    {
        self::assertEqualsWithDelta(1.0, Contrast::relativeLuminance(255, 255, 255), 0.0001);
        self::assertEqualsWithDelta(0.0, Contrast::relativeLuminance(0, 0, 0), 0.0001);
        self::assertEqualsWithDelta(21.0, Contrast::ratio(1.0, 0.0), 0.0001);
        // #767676 on white is the classic 4.54:1
        self::assertEqualsWithDelta(4.54, Contrast::ratio(1.0, Contrast::relativeLuminance(0x76, 0x76, 0x76)), 0.01);
        // DRK red #e60005 with white text (red label variant) >= 4.5:1
        self::assertGreaterThanOrEqual(4.5, Contrast::ratio(1.0, Contrast::relativeLuminance(0xE6, 0x00, 0x05)));
        self::assertSame(Contrast::ratio(0.2, 0.7), Contrast::ratio(0.7, 0.2));
    }

    public static function backgrounds(): \Generator
    {
        $l = static fn(int $v): float => Contrast::relativeLuminance($v, $v, $v);
        yield 'white' => [array_fill(0, 100, 1.0), Contrast::BLACK];
        yield 'black' => [array_fill(0, 100, 0.0), Contrast::WHITE];
        yield 'light grey' => [array_fill(0, 100, $l(200)), Contrast::BLACK];
        yield 'dark blue' => [array_fill(0, 100, Contrast::relativeLuminance(10, 30, 90)), Contrast::WHITE];
        // uniform mid grey: one colour always reaches >= 4.5:1
        yield 'mid grey' => [array_fill(0, 100, $l(119)), Contrast::BLACK];
        yield 'half black, half white' => [array_merge(array_fill(0, 50, 0.0), array_fill(0, 50, 1.0)), Contrast::PLATE];
        yield 'mostly white with few dark pixels (< 5 %)' => [array_merge(array_fill(0, 97, 1.0), array_fill(0, 3, 0.0)), Contrast::BLACK];
        yield 'empty' => [[], Contrast::PLATE];
    }

    /**
     * @param list<float> $luminances
     */
    #[Test]
    #[DataProvider('backgrounds')]
    public function choosesVariantByMeasuredContrast(array $luminances, string $expected): void
    {
        $choice = Contrast::chooseVariant($luminances);
        self::assertSame($expected, $choice['variant']);
        if ($expected !== Contrast::PLATE) {
            self::assertGreaterThanOrEqual(Contrast::MIN_RATIO, max($choice['black'], $choice['white']));
        }
    }

    #[Test]
    public function placementUsesShorterEdgeAndClamps(): void
    {
        // 1200 x 800: 8 % of 800 = 64 px, margin 2 % = 16 px, top right
        self::assertSame(['size' => 64, 'margin' => 16, 'x' => 1120, 'y' => 16], Contrast::placement(1200, 800, 'top-right', 8, 24, 96));
        // huge image: clamped to max
        self::assertSame(96, Contrast::placement(4000, 3000, 'top-right', 8, 24, 96)['size'] ?? null);
        // small image: clamped to min, margin min 4 px, bottom left
        self::assertSame(['size' => 24, 'margin' => 4, 'x' => 4, 'y' => 72], Contrast::placement(150, 100, 'bottom-left', 8, 24, 96));
        // too small for a legible symbol
        self::assertNull(Contrast::placement(100, 71, 'top-right', 8, 24, 96));
    }
}
