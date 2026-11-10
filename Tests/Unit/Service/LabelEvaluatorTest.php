<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Tests\Unit\Service;

use FourViewture\AiLabeling\Domain\Labeling as L;
use FourViewture\AiLabeling\Service\LabelEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LabelEvaluatorTest extends TestCase
{
    public static function cases(): \Generator
    {
        yield 'no AI' => [[L::F_INVOLVEMENT => 0], L::STATUS_NONE, false, ['none']];
        yield 'assisted only' => [[L::F_INVOLVEMENT => 1, L::F_REAL_REFERENCE => 1, L::F_DECEPTIVE => 1], L::STATUS_NONE, false, ['assisted']];
        yield 'deepfake' => [[L::F_INVOLVEMENT => 3, L::F_REAL_REFERENCE => 1, L::F_DECEPTIVE => 1], L::STATUS_REQUIRED, true, ['deepfake']];
        yield 'one criterion -> in doubt' => [[L::F_INVOLVEMENT => 3, L::F_REAL_REFERENCE => 1], L::STATUS_RECOMMENDED, true, ['doubt']];
        yield 'stylised image' => [[L::F_INVOLVEMENT => 3], L::STATUS_OPTIONAL, false, ['noCriteria']];
        yield 'testimonial' => [[L::F_INVOLVEMENT => 3, L::F_SYNTHETIC_PERSON => 1], L::STATUS_REQUIRED, true, ['syntheticPerson']];
        yield 'product altered' => [[L::F_INVOLVEMENT => 2, L::F_PRODUCT_ALTERED => 1], L::STATUS_REQUIRED, true, ['productAltered']];
        yield 'public interest text unreviewed' => [[L::F_INVOLVEMENT => 3, L::F_PUBLIC_INTEREST => 1], L::STATUS_REQUIRED, true, ['publicInterestText']];
        yield 'public interest text reviewed' => [[L::F_INVOLVEMENT => 3, L::F_PUBLIC_INTEREST => 1, L::F_HUMAN_REVIEW => 1], L::STATUS_OPTIONAL, false, ['reviewedText']];
        yield 'forced' => [[L::F_INVOLVEMENT => 3, L::F_MODE => 1], L::STATUS_OPTIONAL, true, ['noCriteria', 'forced']];
        yield 'suppressed although required' => [[L::F_INVOLVEMENT => 3, L::F_PRODUCT_ALTERED => 1, L::F_MODE => 2], L::STATUS_REQUIRED, false, ['productAltered', 'suppressedRequired']];
        yield 'FormEngine array values' => [[L::F_INVOLVEMENT => ['2'], L::F_MODE => ['0'], L::F_REAL_REFERENCE => 1, L::F_DECEPTIVE => 1], L::STATUS_REQUIRED, true, ['deepfake']];
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $reasons
     */
    #[Test]
    #[DataProvider('cases')]
    public function evaluatesAccordingToGuide(array $row, int $status, bool $active, array $reasons): void
    {
        $assessment = (new LabelEvaluator())->evaluate($row);
        self::assertSame($status, $assessment->status);
        self::assertSame($active, $assessment->active);
        self::assertSame($reasons, $assessment->reasons);
    }

    #[Test]
    public function labelTypeFollowsInvolvement(): void
    {
        $evaluator = new LabelEvaluator();
        self::assertSame('modified', $evaluator->evaluate([L::F_INVOLVEMENT => 2, L::F_MODE => 1])->labelType);
        self::assertSame('generated', $evaluator->evaluate([L::F_INVOLVEMENT => 3, L::F_MODE => 1])->labelType);
        self::assertNull($evaluator->evaluate([L::F_INVOLVEMENT => 3])->labelType);
    }
}
