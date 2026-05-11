<?php

namespace App\Support\Bargain;

final class OfferExtractor
{
    /**
     * Best-effort extraction of a PKR amount from free text.
     * Returns a decimal string with 2 fractional digits, or null if not found.
     */
    public static function extractPkrAmount(string $text): ?string
    {
        $t = $text;
        if ($t === '') {
            return null;
        }

        $patterns = [
            '/PKR\s*([0-9][0-9,]*(?:\.[0-9]{1,2})?)\s*(k)?\b/i',
            '/Rs\.?\s*([0-9][0-9,]*(?:\.[0-9]{1,2})?)\s*(k)?\b/i',
            '/\b([0-9][0-9,]*(?:\.[0-9]{1,2})?)\s*(k)?\s*(?:PKR|Rs\.?)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $t, $m) === 1) {
                $raw = str_replace(',', '', (string) $m[1]);
                if (is_numeric($raw)) {
                    $n = (float) $raw;
                    $hasK = isset($m[2]) && is_string($m[2]) && $m[2] !== '';
                    if ($hasK) {
                        $n *= 1000.0;
                    }

                    return number_format($n, 2, '.', '');
                }
            }
        }

        // Informal "11k", "11.4k" → multiply by 1000 (11.4k → 11400.00).
        if (preg_match('/\b(\d+(?:\.\d+)?)\s*k\b/i', $t, $m) === 1) {
            $raw = (string) $m[1];
            if (is_numeric($raw)) {
                return number_format(((float) $raw) * 1000.0, 2, '.', '');
            }
        }

        if (preg_match('/\b([0-9]{3,7}(?:\.[0-9]{1,2})?)\b/', $t, $m) === 1) {
            $raw = (string) $m[1];
            if (is_numeric($raw)) {
                return number_format((float) $raw, 2, '.', '');
            }
        }

        return null;
    }
}
