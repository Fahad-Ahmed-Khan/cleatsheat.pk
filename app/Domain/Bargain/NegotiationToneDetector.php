<?php

namespace App\Domain\Bargain;

final class NegotiationToneDetector
{
    /**
     * Micro-push / guilt phrases (roman Urdu) — paired with small numeric gap for defend-line routing.
     *
     * @return list<string>
     */
    private static function defendPatterns(): array
    {
        return [
            '/\bsirf\b/i',
            '/\bsirf\s+\d+/i',
            '/\bitna\b/i',
            '/\bitna\s+tou/i',
            '/\bsamajh\b/i',
            '/\bsamajh\s+nahi/i',
            '/\bthora\b.*\b(?:aur|or)\b/i',
            '/\b(?:bas|bus)\b.*\b(?:itna|thora)\b/i',
        ];
    }

    public static function shouldDefendMicroPush(string $message, ?string $statedPkr, ?string $shopLinePkr): bool
    {
        if ($statedPkr === null || $statedPkr === '' || $shopLinePkr === null || $shopLinePkr === '') {
            return false;
        }

        $maxGap = (float) config('bargain.defend.max_gap_pkr', 250.0);
        if ($maxGap <= 0.0) {
            return false;
        }

        $gap = abs((float) bcsub($shopLinePkr, $statedPkr, 2));
        if ($gap > $maxGap) {
            return false;
        }

        $t = mb_strtolower($message);
        foreach (self::defendPatterns() as $re) {
            if (preg_match($re, $t) === 1) {
                return true;
            }
        }

        return false;
    }
}
