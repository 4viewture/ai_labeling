<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Tests\Unit\Domain;

use FourViewture\AiLabeling\Domain\DetectionResult;
use FourViewture\AiLabeling\Domain\Labeling as L;
use FourViewture\AiLabeling\Domain\ScanPlan;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScanPlanTest extends TestCase
{
    #[Test]
    public function reportsChangesAndLabelState(): void
    {
        $plan = new ScanPlan(
            new DetectionResult(3, 'Midjourney', ['XMP']),
            [L::F_INVOLVEMENT => 0, L::F_MODE => 0, L::F_ACTIVE => 0, L::F_TOOL => ''],
            [L::F_DETECTED => 1, L::F_DETECTION => 'XMP', L::F_INVOLVEMENT => 3, L::F_MODE => 1, L::F_ACTIVE => 1, L::F_TOOL => 'Midjourney'],
            false
        );
        self::assertSame(['from' => '0', 'to' => '3'], $plan->changes()[L::F_INVOLVEMENT]);
        self::assertSame(['from' => '', 'to' => '1'], $plan->changes()[L::F_DETECTED]);
        self::assertArrayNotHasKey(L::F_DETECTION, $plan->changes(), 'evidence text is not a reported field');
        self::assertTrue($plan->hasChanges());
        self::assertTrue($plan->changesLabelState());
    }

    #[Test]
    public function unchangedPlan(): void
    {
        $values = [L::F_DETECTED => 1, L::F_DETECTION => 'XMP', L::F_INVOLVEMENT => 3, L::F_ACTIVE => 1];
        $plan = new ScanPlan(new DetectionResult(3, null, ['XMP']), $values, $values, false);
        self::assertSame([], $plan->changes());
        self::assertFalse($plan->hasChanges());
        self::assertFalse($plan->changesLabelState());
    }
}
