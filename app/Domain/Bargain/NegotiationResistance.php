<?php

namespace App\Domain\Bargain;

final class NegotiationResistance
{
    /**
     * 0 = far from floor (near list), 100 = at/near policy floor.
     */
    public static function scoreFromShopLine(?string $shopLinePkr, BargainPolicy $policy): int
    {
        $line = $shopLinePkr !== null && $shopLinePkr !== ''
            ? $shopLinePkr
            : $policy->listPrice;

        $line = $policy->clampToAllowedRange($line);
        $den = bcsub($policy->listPrice, $policy->minAllowedPrice, 6);
        if (bccomp($den, '0', 2) <= 0) {
            return 0;
        }

        $num = bcsub($line, $policy->minAllowedPrice, 6);
        if (bccomp($num, '0', 2) < 0) {
            $num = '0';
        }

        $t = (float) bcdiv($num, $den, 6);
        $t = max(0.0, min(1.0, $t));
        $proximityFromMinToList = $t;

        return (int) round((1.0 - $proximityFromMinToList) * 100.0);
    }
}
