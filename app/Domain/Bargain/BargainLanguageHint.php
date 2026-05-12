<?php

namespace App\Domain\Bargain;

final class BargainLanguageHint
{
    private const STRONG_URDU = [
        'bhai', 'bhaijaan', 'yar', 'yaar', 'scene', 'acha', 'achaa', 'theek', 'thik', 'theek hai',
        'karlo', 'kar lo', 'kar dein', 'kar dein?', 'de do', 'de doon', 'ho jaye', 'ho jayega',
        'kitna', 'kitne', 'kya', 'nahi', 'nahee', 'mein', 'main ne', 'mujhe', 'apka', 'aapka',
        'zara', 'thora', 'thori', 'please bhi', 'janab', 'sahab',
    ];

    private const PARTICLE = '/\b(ki|ka|ke|ko|se|me|mein|wala|wali|walay|hain|hai|ho|rahay|rahe)\b/u';

    private const ENGLISH_FORMAL = [
        'would you', 'could you', 'please let', 'i would like', 'appreciate if', 'kindly',
        'usd', 'dollar', 'shipping policy', 'return policy',
    ];

    /**
     * @return 'roman_urdu'|'english'|'mixed'
     */
    public static function fromCustomerText(string $text): string
    {
        $t = mb_strtolower(trim($text));
        if ($t === '') {
            return 'english';
        }

        $urduScore = 0.0;
        foreach (self::STRONG_URDU as $w) {
            if (str_contains($t, $w)) {
                $urduScore += 1.15;
            }
        }

        if (preg_match(self::PARTICLE, $t) === 1) {
            $urduScore += 0.9;
        }

        if (preg_match('/\p{Extended_Pictographic}/u', $text) === 1) {
            $urduScore += 0.35;
        }

        $englishScore = 0.0;
        foreach (self::ENGLISH_FORMAL as $w) {
            if (str_contains($t, $w)) {
                $englishScore += 1.4;
            }
        }

        if (preg_match('/\b(please|thanks|thank you|hello|hi there|regards)\b/i', $t) === 1) {
            $englishScore += 0.35;
        }

        $latinRatio = self::latinToTotalRatio($text);
        if ($latinRatio >= 0.92 && mb_strlen($t) > 40) {
            $englishScore += 0.8;
        }

        // Roman digits PKR amounts alone should not flip to Urdu.
        if (preg_match('/\bpkr\b|rs\.?/i', $t) === 1 && mb_strlen(preg_replace('/[\s\d.,pkr]/iu', '', $t) ?? '') < 6) {
            $englishScore += 1.2;
        }

        if ($urduScore >= 2.8 && $urduScore > $englishScore + 0.8) {
            return 'roman_urdu';
        }

        if ($urduScore >= 1.4 && $englishScore >= 1.0) {
            return 'mixed';
        }

        if ($urduScore >= 1.8 && $englishScore < 1.8) {
            return 'roman_urdu';
        }

        if ($englishScore >= 2.0 && $urduScore < 1.2) {
            return 'english';
        }

        return 'english';
    }

    private static function latinToTotalRatio(string $text): float
    {
        $len = mb_strlen($text);
        if ($len === 0) {
            return 0.0;
        }
        $latin = 0;
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($text, $i, 1);
            if (preg_match('/^[a-zA-Z0-9\s\p{P}]$/u', $ch) === 1) {
                $latin++;
            }
        }

        return $latin / $len;
    }
}
