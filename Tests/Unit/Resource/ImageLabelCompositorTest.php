<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Tests\Unit\Resource;

use FourViewture\AiLabeling\Imaging\Contrast;
use FourViewture\AiLabeling\Resource\Processing\ImageLabelCompositor;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('gd')]
final class ImageLabelCompositorTest extends TestCase
{
    private const PARAMETERS = ['position' => 'top-right', 'sizePercent' => 8.0, 'minSize' => 24, 'maxSize' => 96];

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    /**
     * @param \Closure(\GdImage): void $paint
     */
    private function image(string $type, int $width, int $height, \Closure $paint): string
    {
        $image = imagecreatetruecolor($width, $height);
        $paint($image);
        $file = sys_get_temp_dir() . '/ai-label-test-' . bin2hex(random_bytes(4)) . '.' . $type;
        match ($type) {
            'jpg' => imagejpeg($image, $file, 95),
            'png' => imagepng($image, $file),
            'webp' => imagewebp($image, $file, 95),
        };
        $this->files[] = $file;
        return $file;
    }

    private static function luminanceAt(string $file, int $x, int $y): float
    {
        $image = imagecreatefromstring((string)file_get_contents($file));
        $rgb = imagecolorat($image, $x, $y);
        return Contrast::relativeLuminance(($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
    }

    #[Test]
    public function burnsBlackSymbolIntoTopRightOfLightImage(): void
    {
        $file = $this->image('jpg', 1200, 800, static fn($i) => imagefill($i, 0, 0, imagecolorallocate($i, 250, 250, 250)));
        self::assertSame(ImageLabelCompositor::RESULT_DONE, (new ImageLabelCompositor())->composite($file, self::PARAMETERS));
        self::assertSame([1200, 800], array_slice((array)getimagesize($file), 0, 2), 'dimensions unchanged');
        // symbol 64 px at x 1120..1184, y 16..80: near the disc edge (inside, outside the letters) it is dark
        self::assertLessThan(0.05, self::luminanceAt($file, 1152, 20));
        // outside the symbol untouched
        self::assertGreaterThan(0.9, self::luminanceAt($file, 1100, 48));
        self::assertGreaterThan(0.9, self::luminanceAt($file, 40, 40));
    }

    #[Test]
    public function burnsWhiteSymbolIntoDarkImage(): void
    {
        $file = $this->image('png', 800, 600, static fn($i) => imagefill($i, 0, 0, imagecolorallocate($i, 10, 20, 40)));
        self::assertSame(ImageLabelCompositor::RESULT_DONE, (new ImageLabelCompositor())->composite($file, self::PARAMETERS));
        // 8 % of 600 = 48 px, margin 12: x 740..788, y 12..60
        self::assertGreaterThan(0.9, self::luminanceAt($file, 764, 15));
    }

    #[Test]
    public function drawsBackingPlateOnBusyBackground(): void
    {
        $file = $this->image('webp', 1000, 1000, static function ($i): void {
            $black = imagecolorallocate($i, 0, 0, 0);
            $white = imagecolorallocate($i, 255, 255, 255);
            for ($x = 0; $x < 1000; $x += 4) {
                imagefilledrectangle($i, $x, 0, $x + 1, 999, $black);
                imagefilledrectangle($i, $x + 2, 0, $x + 3, 999, $white);
            }
        });
        self::assertSame(ImageLabelCompositor::RESULT_DONE, (new ImageLabelCompositor())->composite($file, self::PARAMETERS));
        // 80 px symbol at x 900..980, y 20..100; plate ring (14 % larger) is white just outside the disc
        self::assertGreaterThan(0.8, self::luminanceAt($file, 940, 16));
        self::assertLessThan(0.1, self::luminanceAt($file, 940, 24));
    }

    #[Test]
    public function skipsTooSmallImagesAndFailsGracefully(): void
    {
        $small = $this->image('jpg', 60, 60, static fn($i) => imagefill($i, 0, 0, imagecolorallocate($i, 255, 255, 255)));
        $before = md5_file($small);
        self::assertSame(ImageLabelCompositor::RESULT_SKIPPED, (new ImageLabelCompositor())->composite($small, self::PARAMETERS));
        self::assertSame($before, md5_file($small));

        $broken = sys_get_temp_dir() . '/ai-label-test-broken.jpg';
        file_put_contents($broken, 'no image');
        $this->files[] = $broken;
        self::assertSame(ImageLabelCompositor::RESULT_FAILED, (new ImageLabelCompositor())->composite($broken, self::PARAMETERS));
    }
}
