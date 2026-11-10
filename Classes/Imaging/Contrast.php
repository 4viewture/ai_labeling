<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Imaging;

/**
 * WCAG 2.x contrast calculation and the choice of the symbol variant for burned-in labels.
 *
 * The EU symbol is a filled disc with the letters cut out in the opposite colour, so the letters are
 * always readable; what has to stand out from the image is the disc (non-text contrast, WCAG 1.4.11:
 * >= 3:1). The target corner is measured robustly: black is checked against the darkest 5 % of the
 * sampled pixels, white against the brightest 5 %. If neither reaches 3:1 (busy or mid-grey
 * backgrounds), an opaque backing plate is drawn behind the black symbol.
 */
final class Contrast
{
    public const MIN_RATIO = 3.0;

    public const BLACK = 'black';
    public const WHITE = 'white';
    /** black symbol on an opaque white backing plate */
    public const PLATE = 'plate';

    /**
     * Relative luminance of an sRGB colour (0–255 per channel), WCAG 2.x definition.
     */
    public static function relativeLuminance(int $red, int $green, int $blue): float
    {
        $linear = static function (int $channel): float {
            $c = max(0, min(255, $channel)) / 255;
            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        return 0.2126 * $linear($red) + 0.7152 * $linear($green) + 0.0722 * $linear($blue);
    }

    public static function ratio(float $luminanceA, float $luminanceB): float
    {
        $lighter = max($luminanceA, $luminanceB);
        $darker = min($luminanceA, $luminanceB);
        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * @param list<float> $luminances sampled relative luminances of the target area
     * @return array{variant: self::BLACK|self::WHITE|self::PLATE, black: float, white: float}
     */
    public static function chooseVariant(array $luminances): array
    {
        if ($luminances === []) {
            return ['variant' => self::PLATE, 'black' => 0.0, 'white' => 0.0];
        }
        sort($luminances);
        $count = count($luminances);
        $darkest = $luminances[(int)floor(($count - 1) * 0.05)];
        $brightest = $luminances[(int)ceil(($count - 1) * 0.95)];
        $black = self::ratio(0.0, $darkest);
        $white = self::ratio(1.0, $brightest);
        if (max($black, $white) < self::MIN_RATIO) {
            $variant = self::PLATE;
        } else {
            $variant = $black >= $white ? self::BLACK : self::WHITE;
        }
        return ['variant' => $variant, 'black' => $black, 'white' => $white];
    }

    /**
     * Symbol size and offset for an image.
     *
     * @return array{size: int, margin: int, x: int, y: int}|null null = image too small for a legible symbol
     */
    public static function placement(int $width, int $height, string $position, float $sizePercent, int $minSize, int $maxSize): ?array
    {
        $shorter = min($width, $height);
        if ($shorter < 3 * $minSize) {
            return null;
        }
        $size = (int)round($shorter * $sizePercent / 100);
        $size = max($minSize, min($maxSize, $size));
        $margin = max(4, (int)round($shorter * 0.02));
        $x = str_ends_with($position, 'left') ? $margin : $width - $margin - $size;
        $y = str_starts_with($position, 'bottom') ? $height - $margin - $size : $margin;
        return ['size' => $size, 'margin' => $margin, 'x' => $x, 'y' => $y];
    }
}
