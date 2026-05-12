<?php

namespace App\Domain\Bargain;

use App\Models\ProductVariant;

final readonly class BargainPolicy
{
    private function __construct(
        public string $listPrice,
        public string $minAllowedPrice,
        public bool $bargainEnabled,
    ) {}

    public static function fromVariant(ProductVariant $variant): self
    {
        $variant->loadMissing('product');

        $enabled = (bool) config('bargain.enabled', true)
            && (bool) $variant->is_active
            && (bool) $variant->product?->is_active
            && (bool) $variant->bargain_enabled;

        $list = number_format((float) (string) $variant->price, 2, '.', '');

        $configuredMin = $variant->bargain_min_price !== null
            ? number_format((float) (string) $variant->bargain_min_price, 2, '.', '')
            : $list;

        if (bccomp($configuredMin, $list, 2) === 1) {
            $configuredMin = $list;
        }

        $pct = $variant->bargain_max_discount_percent !== null
            ? number_format((float) (string) $variant->bargain_max_discount_percent, 2, '.', '')
            : '0.00';

        $ratio = bcsub('1.000000', bcdiv($pct, '100', 6), 6);
        $discountedFloor = bcmul($list, $ratio, 2);

        $minAllowed = self::bcMax([$configuredMin, $discountedFloor]);
        $minAllowed = self::bcMin([$minAllowed, $list]);

        return new self($list, $minAllowed, $enabled);
    }

    public function isAllowedPrice(string $price): bool
    {
        return bccomp($price, $this->minAllowedPrice, 2) >= 0 && bccomp($price, $this->listPrice, 2) <= 0;
    }

    public function clampToAllowedRange(string $price): string
    {
        $p = $price;
        if (bccomp($p, $this->listPrice, 2) === 1) {
            $p = $this->listPrice;
        }
        if (bccomp($p, $this->minAllowedPrice, 2) === -1) {
            $p = $this->minAllowedPrice;
        }

        return $p;
    }

    /**
     * When the customer's offer is already within allowed range, optionally nudge the working price
     * slightly toward list to improve margin (still fair; capped by gap).
     *
     * @param  string  $stated  Already-clamped customer amount within [minAllowed, list].
     */
    public function nudgeInRangeStatedPrice(string $stated): string
    {
        $stated = $this->clampToAllowedRange($stated);

        if (! filter_var(config('bargain.in_range_nudge.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return $stated;
        }

        if (bccomp($stated, $this->listPrice, 2) >= 0) {
            return $this->listPrice;
        }

        $gap = bcsub($this->listPrice, $stated, 2);
        if (bccomp($gap, '0.00', 2) <= 0) {
            return $stated;
        }

        $minStep = number_format((float) config('bargain.in_range_nudge.min_step_pkr', 25), 2, '.', '');
        $fraction = (float) config('bargain.in_range_nudge.max_fraction_of_gap', 0.4);
        $fromGap = bcmul($gap, number_format($fraction, 6, '.', ''), 2);
        $bump = bccomp($minStep, $fromGap, 2) >= 0 ? $minStep : $fromGap;
        if (bccomp($bump, $gap, 2) === 1) {
            $bump = $gap;
        }

        $nudged = bcadd($stated, $bump, 2);
        $nudged = $this->clampToAllowedRange($nudged);

        // Ensure shop offers feel natural: multiples of 50 PKR.
        $nudged = $this->roundShopOfferTo50($nudged);

        if (bccomp($nudged, $stated, 2) <= 0) {
            return $stated;
        }

        return $nudged;
    }

    private function roundShopOfferTo50(string $price): string
    {
        $p = $this->clampToAllowedRange($price);

        // effective floor is next 50 above minAllowed (keeps offers multiples of 50 without violating minAllowed).
        $minInt = (int) round((float) $this->minAllowedPrice, 0);
        $floor = (int) (intdiv($minInt + 49, 50) * 50);

        $pi = (int) round((float) $p, 0);
        if ($pi < $floor) {
            $pi = $floor;
        }

        // Round DOWN to 50 to avoid jumping up (e.g. 952 -> 1000).
        $roundedUp = (int) (intdiv($pi, 50) * 50);
        $listInt = (int) round((float) $this->listPrice, 0);
        if ($roundedUp > $listInt) {
            $roundedUp = (int) (intdiv($listInt, 50) * 50);
        }
        if ($roundedUp < $floor) {
            $roundedUp = $floor;
        }

        $out = number_format((float) $roundedUp, 2, '.', '');

        return $this->clampToAllowedRange($out);
    }

    /**
     * When the customer is below the policy floor: compute a deterministic, seeded counter-offer
     * that steps down from list toward min allowed with rounded, human-feeling increments.
     *
     * Seed material should include session + customer message id to keep behavior stable per turn.
     */
    public function steppedCounterBelowMin(?string $previousShopOffer, string $seedMaterial, ?ConcessionContext $context = null): string
    {
        $next = ConcessionStepCalculator::nextOffer(
            $this->listPrice,
            $this->minAllowedPrice,
            $previousShopOffer,
            $seedMaterial,
            $context,
        );

        return $this->clampToAllowedRange($next);
    }

    /**
     * @param  array<int, string>  $values
     */
    private static function bcMax(array $values): string
    {
        $best = $values[0];
        foreach ($values as $v) {
            if (bccomp($v, $best, 2) === 1) {
                $best = $v;
            }
        }

        return $best;
    }

    /**
     * @param  array<int, string>  $values
     */
    private static function bcMin(array $values): string
    {
        $best = $values[0];
        foreach ($values as $v) {
            if (bccomp($v, $best, 2) === -1) {
                $best = $v;
            }
        }

        return $best;
    }
}
