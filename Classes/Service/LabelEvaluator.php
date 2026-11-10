<?php

declare(strict_types=1);

namespace FourViewture\AiLabeling\Service;

use FourViewture\AiLabeling\Domain\Assessment;
use FourViewture\AiLabeling\Domain\Labeling;

/**
 * Implements the decision aid of the guide "KI-generierte Inhalte kennzeichnen"
 * (Mittelstand-Digital Zentrum Berlin) for Art. 50 EU AI Act.
 *
 * Keep in sync with Resources/Public/JavaScript/label-guide.js (live preview in the backend form).
 */
final class LabelEvaluator
{
    /**
     * @param array<string, mixed> $row record (DB row or FormEngine databaseRow)
     */
    public function evaluate(array $row): Assessment
    {
        $involvement = self::int($row, Labeling::F_INVOLVEMENT);
        $mode = self::int($row, Labeling::F_MODE);

        $reasons = [];
        $status = Labeling::STATUS_NONE;

        if ($involvement === Labeling::INVOLVEMENT_NONE) {
            $reasons[] = 'none';
        } elseif ($involvement === Labeling::INVOLVEMENT_ASSISTED) {
            $reasons[] = 'assisted';
        } else {
            $realReference = self::bool($row, Labeling::F_REAL_REFERENCE);
            $deceptive = self::bool($row, Labeling::F_DECEPTIVE);
            $publicInterest = self::bool($row, Labeling::F_PUBLIC_INTEREST);
            $humanReview = self::bool($row, Labeling::F_HUMAN_REVIEW);

            if ($realReference && $deceptive) {
                $reasons[] = 'deepfake';
            }
            if (self::bool($row, Labeling::F_SYNTHETIC_PERSON)) {
                $reasons[] = 'syntheticPerson';
            }
            if (self::bool($row, Labeling::F_PRODUCT_ALTERED)) {
                $reasons[] = 'productAltered';
            }
            if ($publicInterest && !$humanReview) {
                $reasons[] = 'publicInterestText';
            }

            if ($reasons !== []) {
                $status = Labeling::STATUS_REQUIRED;
            } elseif ($realReference xor $deceptive) {
                // only one deepfake criterion: e.g. realistic but fictitious landscape -> label in case of doubt
                $status = Labeling::STATUS_RECOMMENDED;
                $reasons[] = 'doubt';
            } elseif ($publicInterest && $humanReview) {
                $status = Labeling::STATUS_OPTIONAL;
                $reasons[] = 'reviewedText';
            } else {
                $status = Labeling::STATUS_OPTIONAL;
                $reasons[] = 'noCriteria';
            }
        }

        $active = match ($mode) {
            Labeling::MODE_FORCE => true,
            Labeling::MODE_SUPPRESS => false,
            default => $status >= Labeling::STATUS_RECOMMENDED,
        };

        if ($mode === Labeling::MODE_FORCE) {
            $reasons[] = 'forced';
        } elseif ($mode === Labeling::MODE_SUPPRESS) {
            $reasons[] = $status === Labeling::STATUS_REQUIRED ? 'suppressedRequired' : 'suppressed';
        }

        $labelType = null;
        if ($active) {
            $labelType = $involvement === Labeling::INVOLVEMENT_MODIFIED ? 'modified' : 'generated';
        }

        return new Assessment($status, $active, $reasons, $labelType);
    }

    /**
     * FormEngine delivers select values as arrays, DB rows as scalars.
     *
     * @param array<string, mixed> $row
     */
    private static function int(array $row, string $field): int
    {
        $value = $row[$field] ?? 0;
        if (is_array($value)) {
            $value = reset($value);
        }
        return (int)$value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function bool(array $row, string $field): bool
    {
        return self::int($row, $field) === 1;
    }
}
