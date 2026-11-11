<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Tests\Unit\Service;

use FourViewture\AiLabeling\Domain\DetectionResult;
use FourViewture\AiLabeling\Domain\Labeling as L;
use FourViewture\AiLabeling\Service\AutoLabeler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AutoLabelerTest extends TestCase
{
    public static function states(): \Generator
    {
        yield 'new file' => [[], false];
        yield 'editor set involvement, never detected' => [[L::F_INVOLVEMENT => 2], true];
        yield 'detected before, unchanged' => [[L::F_INVOLVEMENT => 3, L::F_DETECTED => 1], false];
        yield 'detected before, editor changed to modified' => [[L::F_INVOLVEMENT => 2, L::F_DETECTED => 1], true];
        yield 'detected before, editor set "no AI"' => [[L::F_INVOLVEMENT => 0, L::F_DETECTED => 1], true];
    }

    /**
     * @param array<string, int> $current
     */
    #[Test]
    #[DataProvider('states')]
    public function rescansKeepEditorialDecisions(array $current, bool $manual): void
    {
        self::assertSame($manual, AutoLabeler::isManuallyMaintained($current, new DetectionResult(3, 'Midjourney', ['XMP'])));
    }
}
