<?php

namespace App\Domain\Bargain;

final class BargainLanguageHint
{
    /**
     * @return 'roman_urdu'|'english'
     */
    public static function fromCustomerText(string $text): string
    {
        $t = mb_strtolower($text);
        if ($t === '') {
            return 'english';
        }

        // Simple, cheap heuristic: common Roman Urdu tokens or patterns.
        $tokens = [
            'bhai', 'bhaijaan', 'bro', 'acha', 'achaa', 'theek', 'thik', 'ok', 'kitna', 'kitne', 'kya',
            'hai', 'haan', 'han', 'nahi', 'nahee', 'krdo', 'kardo', 'kar do', 'karna', 'dein', 'dena',
            'please', 'bata', 'batao', 'final', 'last', 'scene', 'yaar', 'sir', 'janab',
        ];

        $hits = 0;
        foreach ($tokens as $w) {
            if (str_contains($t, $w)) {
                $hits++;
                if ($hits >= 2) {
                    return 'roman_urdu';
                }
            }
        }

        // If text contains lots of "ki/ka/ke" constructs or Urdu-ish short words, treat as Roman Urdu.
        if (preg_match('/\b(ki|ka|ke|se|me|mein|wala|wali|walay)\b/u', $t) === 1) {
            return 'roman_urdu';
        }

        return 'english';
    }
}

