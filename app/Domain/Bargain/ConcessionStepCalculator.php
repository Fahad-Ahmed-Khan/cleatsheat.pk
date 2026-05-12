<?php

namespace App\Domain\Bargain;

final class ConcessionStepCalculator
{
    private const OFFER_DENOM_PKR = 50;

    /**
     * Compute the next shop offer when the customer is below min allowed.
     * All inputs/outputs are PKR decimal strings with 2 digits (e.g. "12999.00").
     */
    public static function nextOffer(
        string $listPrice,
        string $minAllowedPrice,
        ?string $previousShopOffer,
        string $seedMaterial,
        ?ConcessionContext $context = null,
    ): string {
        $list = self::toIntPkr($listPrice);
        $min = self::toIntPkr($minAllowedPrice);
        $prev = $previousShopOffer !== null && $previousShopOffer !== '' ? self::toIntPkr($previousShopOffer) : $list;

        if ($prev > $list) {
            $prev = $list;
        }
        if ($prev < $min) {
            $prev = $min;
        }

        $gap = $prev - $min;
        if ($gap <= 0) {
            return self::fmt(self::effectiveFloor($min));
        }

        $enabled = filter_var(config('bargain.counter.concession.enabled', true), FILTER_VALIDATE_BOOLEAN);
        if (! $enabled) {
            $fixed = (int) config('bargain.counter.min_step', 100);
            $candidate = max($min, $prev - max(1, $fixed));

            return self::fmt(self::normalizeOffer($candidate, $min, $list));
        }

        $minStep = (int) config('bargain.counter.concession.min_step_pkr', 25);
        $maxStepFrac = (float) config('bargain.counter.concession.max_step_fraction_of_gap', 0.35);
        $randomness = (float) config('bargain.counter.concession.randomness', 0.18);

        if ($context !== null) {
            $resRatio = max(0, min(100, $context->resistanceScore)) / 100.0;
            $scale = 1.0 - ($resRatio * (float) config('bargain.resistance.max_step_frac_resistance_scale', 0.55));
            $maxStepFrac *= max(0.12, $scale);

            $cc = max(0, $context->concessionCount);
            $w = (float) config('bargain.resistance.concession_count_curve_weight', 0.05);
            $stub = $context->stubbornCustomerMode ? 0.08 : 0.0;
            $streak = max(0, $context->sameOfferStreakAtEnd - 1) * 0.02;
            $progressBoost = min(0.35, $cc * $w + $stub + $streak);

            if ($context->integrityFloorPkr !== null && $context->integrityFloorPkr !== '') {
                $ifloor = self::toIntPkr($context->integrityFloorPkr);
                $gfi = max(0, $prev - $ifloor);
                if ($gfi > 0 && $gfi < 450) {
                    $maxStepFrac *= 0.4;
                }
            }
        } else {
            $progressBoost = 0.0;
        }

        $totalRoom = max(1, $list - $min);
        $progress = 1.0 - ($gap / $totalRoom); // 0 early; -> 1 near floor
        $progress = max(0.0, min(1.0, $progress + $progressBoost));

        $fracHigh = 0.22;
        $fracLow = 0.06;
        $frac = ($fracHigh * (1.0 - $progress)) + ($fracLow * $progress);

        $stepFromGap = (int) ceil($gap * $frac);
        $maxStep = (int) floor($gap * $maxStepFrac);
        $step = self::clampInt($stepFromGap, $minStep, max($minStep, $maxStep));

        $rng = new SeededRng($seedMaterial);
        $r = $rng->float01(); // [0,1)
        $jitter = 1.0 + ((($r * 2.0) - 1.0) * $randomness);
        $step = (int) round($step * $jitter);
        $step = max($minStep, $step);

        // Always round offers/steps to natural 50 PKR denominations.
        $step = max($minStep, self::roundDownInt($step, self::OFFER_DENOM_PKR));
        $step = min($step, $gap);

        $candidate = max($min, $prev - $step);

        return self::fmt(self::normalizeOffer($candidate, $min, $list));
    }

    private static function toIntPkr(string $price): int
    {
        return (int) round((float) $price, 0);
    }

    private static function fmt(int $pkr): string
    {
        return number_format((float) $pkr, 2, '.', '');
    }

    private static function clampInt(int $value, int $min, int $max): int
    {
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }

        return $value;
    }

    private static function roundDownInt(int $value, int $denom): int
    {
        if ($denom <= 1) {
            return $value;
        }

        return intdiv($value, $denom) * $denom;
    }

    private static function roundUpInt(int $value, int $denom): int
    {
        if ($denom <= 1) {
            return $value;
        }

        return (int) (intdiv($value + ($denom - 1), $denom) * $denom);
    }

    private static function effectiveFloor(int $minAllowed): int
    {
        return self::roundUpInt($minAllowed, self::OFFER_DENOM_PKR);
    }

    private static function normalizeOffer(int $candidate, int $minAllowed, int $list): int
    {
        $floor = self::effectiveFloor($minAllowed);
        $c = min($candidate, $list);
        $c = max($c, $floor);
        $c = self::roundDownInt($c, self::OFFER_DENOM_PKR);
        if ($c < $floor) {
            $c = $floor;
        }

        return $c;
    }
}
