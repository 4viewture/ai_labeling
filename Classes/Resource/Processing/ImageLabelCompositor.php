<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Resource\Processing;

use FourViewture\AiLabeling\Imaging\Contrast;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Composites the compact EU "AI" symbol (Resources/Private/Images/ai-symbol-{black,white}.png,
 * rasterized from the official SVGs by Build/rasterize-icons.sh) onto a local raster image, in place.
 * GD is used if available, Imagick otherwise. Any failure returns false (caller logs, no label).
 */
final class ImageLabelCompositor implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const RESULT_DONE = 'done';
    public const RESULT_SKIPPED = 'skipped';
    public const RESULT_FAILED = 'failed';

    /** Sample grid per axis for the contrast measurement */
    private const SAMPLES = 24;

    /** Backing plate radius relative to the symbol */
    private const PLATE_FACTOR = 1.14;

    /**
     * @param array{position: string, sizePercent: float, minSize: int, maxSize: int} $parameters
     * @return self::RESULT_*
     */
    public function composite(string $path, array $parameters): string
    {
        $info = @getimagesize($path);
        if (!is_array($info) || ($info[0] ?? 0) <= 0 || ($info[1] ?? 0) <= 0) {
            return self::RESULT_FAILED;
        }
        $placement = Contrast::placement((int)$info[0], (int)$info[1], $parameters['position'], $parameters['sizePercent'], $parameters['minSize'], $parameters['maxSize']);
        if ($placement === null) {
            return self::RESULT_SKIPPED;
        }
        $mime = (string)($info['mime'] ?? '');
        try {
            if (self::gdSupports($mime)) {
                return $this->compositeWithGd($path, $mime, $placement);
            }
            if (extension_loaded('imagick')) {
                return $this->compositeWithImagick($path, $placement);
            }
        } catch (\Throwable $e) {
            $this->logger?->warning('AI label could not be burned into {path}: {message}', ['path' => $path, 'message' => $e->getMessage()]);
            return self::RESULT_FAILED;
        }
        $this->logger?->warning('AI label could not be burned into {path}: neither GD nor Imagick supports {mime}', ['path' => $path, 'mime' => $mime]);
        return self::RESULT_FAILED;
    }

    public static function symbolPath(string $colour): string
    {
        return dirname(__DIR__, 3) . '/Resources/Private/Images/ai-symbol-' . $colour . '.png';
    }

    private static function gdSupports(string $mime): bool
    {
        return match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') && function_exists('imagejpeg'),
            'image/png' => function_exists('imagecreatefrompng') && function_exists('imagepng'),
            'image/webp' => function_exists('imagecreatefromwebp') && function_exists('imagewebp'),
            'image/gif' => function_exists('imagecreatefromgif') && function_exists('imagegif'),
            default => false,
        } && function_exists('imagecopyresampled');
    }

    /**
     * @param array{size: int, margin: int, x: int, y: int} $placement
     * @return self::RESULT_*
     */
    private function compositeWithGd(string $path, string $mime, array $placement): string
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif' => @imagecreatefromgif($path),
        };
        if ($image === false) {
            return self::RESULT_FAILED;
        }
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        $choice = Contrast::chooseVariant($this->sampleGd($image, $placement));
        imagealphablending($image, true);
        $size = $placement['size'];
        if ($choice['variant'] === Contrast::PLATE) {
            $plate = (int)round($size * self::PLATE_FACTOR);
            $offset = (int)round(($plate - $size) / 2);
            $this->drawSymbolGd($image, Contrast::WHITE, $placement['x'] - $offset, $placement['y'] - $offset, $plate);
            $this->drawSymbolGd($image, Contrast::BLACK, $placement['x'], $placement['y'], $size);
        } else {
            $this->drawSymbolGd($image, $choice['variant'], $placement['x'], $placement['y'], $size);
        }

        $quality = static fn(string $key): int => max(1, min(100, (int)($GLOBALS['TYPO3_CONF_VARS']['GFX'][$key] ?? 85)));
        $saved = match ($mime) {
            'image/jpeg' => imagejpeg($image, $path, $quality('jpg_quality')),
            'image/png' => imagesavealpha($image, true) && imagepng($image, $path),
            'image/webp' => imagesavealpha($image, true) && imagewebp($image, $path, isset($GLOBALS['TYPO3_CONF_VARS']['GFX']['webp_quality']) ? $quality('webp_quality') : 85),
            'image/gif' => imagegif($image, $path),
        };
        return $saved ? self::RESULT_DONE : self::RESULT_FAILED;
    }

    /**
     * @param \GdImage $image
     * @param array{size: int, x: int, y: int} $placement
     * @return list<float>
     */
    private function sampleGd($image, array $placement): array
    {
        $luminances = [];
        $width = imagesx($image);
        $height = imagesy($image);
        $step = max(1, $placement['size'] / self::SAMPLES);
        for ($sy = 0; $sy < self::SAMPLES; $sy++) {
            for ($sx = 0; $sx < self::SAMPLES; $sx++) {
                $x = min($width - 1, max(0, (int)($placement['x'] + $sx * $step)));
                $y = min($height - 1, max(0, (int)($placement['y'] + $sy * $step)));
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha > 63) {
                    // (semi) transparent: the page background is unknown -> worst case on both sides
                    $luminances[] = 0.0;
                    $luminances[] = 1.0;
                    continue;
                }
                $luminances[] = Contrast::relativeLuminance(($rgba >> 16) & 0xFF, ($rgba >> 8) & 0xFF, $rgba & 0xFF);
            }
        }
        return $luminances;
    }

    /**
     * @param \GdImage $image
     */
    private function drawSymbolGd($image, string $colour, int $x, int $y, int $size): void
    {
        $symbol = @imagecreatefrompng(self::symbolPath($colour));
        if ($symbol === false) {
            throw new \RuntimeException('Symbol ' . $colour . ' not readable', 1762790501);
        }
        $scaled = imagecreatetruecolor($size, $size);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagecopyresampled($scaled, $symbol, 0, 0, 0, 0, $size, $size, imagesx($symbol), imagesy($symbol));
        imagecopy($image, $scaled, $x, $y, 0, 0, $size, $size);
    }

    /**
     * @param array{size: int, x: int, y: int} $placement
     * @return self::RESULT_*
     */
    private function compositeWithImagick(string $path, array $placement): string
    {
        $image = new \Imagick($path);
        if ($image->getNumberImages() > 1) {
            return self::RESULT_SKIPPED;
        }
        $luminances = [];
        $step = max(1, $placement['size'] / self::SAMPLES);
        for ($sy = 0; $sy < self::SAMPLES; $sy++) {
            for ($sx = 0; $sx < self::SAMPLES; $sx++) {
                $color = $image->getImagePixelColor((int)($placement['x'] + $sx * $step), (int)($placement['y'] + $sy * $step))->getColor(2);
                if (($color['a'] ?? 255) < 128) {
                    $luminances[] = 0.0;
                    $luminances[] = 1.0;
                    continue;
                }
                $luminances[] = Contrast::relativeLuminance((int)$color['r'], (int)$color['g'], (int)$color['b']);
            }
        }
        $choice = Contrast::chooseVariant($luminances);
        $size = $placement['size'];
        $draw = function (string $colour, int $x, int $y, int $symbolSize) use ($image): void {
            $symbol = new \Imagick(self::symbolPath($colour));
            $symbol->resizeImage($symbolSize, $symbolSize, \Imagick::FILTER_LANCZOS, 1);
            $image->compositeImage($symbol, \Imagick::COMPOSITE_OVER, $x, $y);
            $symbol->clear();
        };
        if ($choice['variant'] === Contrast::PLATE) {
            $plate = (int)round($size * self::PLATE_FACTOR);
            $offset = (int)round(($plate - $size) / 2);
            $draw(Contrast::WHITE, $placement['x'] - $offset, $placement['y'] - $offset, $plate);
            $draw(Contrast::BLACK, $placement['x'], $placement['y'], $size);
        } else {
            $draw($choice['variant'], $placement['x'], $placement['y'], $size);
        }
        $saved = $image->writeImage($path);
        $image->clear();
        return $saved ? self::RESULT_DONE : self::RESULT_FAILED;
    }
}
