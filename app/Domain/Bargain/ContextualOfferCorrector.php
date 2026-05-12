<?php

namespace App\Domain\Bargain;

/**
 * Suggest typo-corrected PKR amounts using shop line / list context only (no floor exposure).
 */
final readonly class ContextualOfferCorrection
{
    public function __construct(
        public ?string $corrected,
        public float $confidence,
    ) {}
}

final class ContextualOfferCorrector
{
    /**
     * @param  list<string>  $recentCustomerAmounts  newest last, decimal strings
     */
    public function correct(
        string $rawParsed,
        string $listPrice,
        ?string $currentOffer,
        array $recentCustomerAmounts,
    ): ContextualOfferCorrection {
        $raw = $rawParsed;
        if ($currentOffer === null || bccomp($currentOffer, '0', 2) !== 1) {
            return new ContextualOfferCorrection(null, 0.0);
        }

        if (bccomp($raw, $currentOffer, 2) >= 0) {
            return new ContextualOfferCorrection(null, 0.0);
        }

        $dRaw = $this->digitsOnly($raw);
        $dShop = $this->digitsOnly($currentOffer);
        if ($dRaw === '' || $dShop === '') {
            return new ContextualOfferCorrection(null, 0.0);
        }

        $dist = levenshtein($dRaw, $dShop);
        $lenDiff = abs(strlen($dRaw) - strlen($dShop));

        // Classic missing-digit typo (e.g. 1150 vs 11150): small edit distance vs shop line.
        if ($dist <= 2 && $lenDiff <= 2) {
            $gapRaw = bcsub($currentOffer, $raw, 2);
            $gapList = bcsub($listPrice, $raw, 2);
            if (bccomp($gapRaw, '0', 2) === 1 && bccomp($gapList, '0', 2) === 1) {
                $ratio = bcdiv($gapRaw, $currentOffer, 4);
                if (bccomp($ratio, '0.35', 4) === 1) {
                    return new ContextualOfferCorrection($currentOffer, 0.88);
                }
            }
        }

        // Customer trending up: small step typo toward shop (e.g. 7000 -> 7500 when shop 7600) — conservative.
        if (count($recentCustomerAmounts) >= 1) {
            $prev = $recentCustomerAmounts[array_key_last($recentCustomerAmounts)];
            if (bccomp($raw, $prev, 2) === 1 && bccomp($raw, $currentOffer, 2) === -1) {
                $step = bcsub($raw, $prev, 2);
                if (bccomp($step, '0', 2) === 1 && bccomp($step, '5000.00', 2) !== 1) {
                    $nearShop = bcsub($currentOffer, $raw, 2);
                    if (bccomp($nearShop, '0', 2) === 1 && bccomp($nearShop, '2500.00', 2) !== 1) {
                        return new ContextualOfferCorrection(null, 0.0);
                    }
                }
            }
        }

        return new ContextualOfferCorrection(null, 0.0);
    }

    private function digitsOnly(string $amount): string
    {
        return preg_replace('/\D+/', '', explode('.', $amount, 2)[0] ?? $amount) ?? '';
    }
}
