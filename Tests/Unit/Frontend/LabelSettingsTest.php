<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Tests\Unit\Frontend;

use FourViewture\AiLabeling\Frontend\LabelSettings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LabelSettingsTest extends TestCase
{
    #[Test]
    public function defaultsApplyWithoutTypoScript(): void
    {
        $settings = LabelSettings::fromTypoScript([]);
        self::assertTrue($settings->includeCss());
        self::assertTrue($settings->contentEnabled());
        self::assertTrue($settings->textVisible());
        self::assertFalse($settings->secondLayer());
        self::assertTrue($settings->burnIn());
        self::assertSame('top-right', $settings->imagePosition());
        self::assertSame(8.0, $settings->imageSizePercent());
        self::assertSame(24, $settings->imageMinSize());
        self::assertSame(96, $settings->imageMaxSize());
        self::assertTrue($settings->altText());
        self::assertTrue($settings->mediaOverlay());
    }

    #[Test]
    public function readsTypoScriptAndSiteSettingValues(): void
    {
        $settings = LabelSettings::fromTypoScript([
            'css.' => ['include' => '0'],
            // TYPO3 13+ site settings write bool false as empty string into the constants
            'content.' => ['enable' => ''],
            'label.' => ['text' => 'hidden', 'secondLayer' => '1'],
            'images.' => ['position' => 'bottom-left', 'sizePercent' => '10,5', 'minSize' => '30', 'maxSize' => '20', 'burnIn' => '{$plugin.tx_ailabeling.images.burnIn}'],
        ]);
        self::assertFalse($settings->includeCss());
        self::assertFalse($settings->contentEnabled());
        self::assertFalse($settings->textVisible());
        self::assertTrue($settings->secondLayer());
        self::assertTrue($settings->burnIn(), 'undefined constant falls back to default');
        self::assertSame('bottom-left', $settings->imagePosition());
        self::assertSame(10.5, $settings->imageSizePercent());
        self::assertSame(30, $settings->imageMaxSize(), 'max is never below min');
    }

    #[Test]
    public function invalidValuesFallBackToDefaults(): void
    {
        $settings = LabelSettings::fromTypoScript(['images.' => ['position' => 'center', 'sizePercent' => '90']]);
        self::assertSame('top-right', $settings->imagePosition());
        self::assertSame(8.0, $settings->imageSizePercent());
    }
}
