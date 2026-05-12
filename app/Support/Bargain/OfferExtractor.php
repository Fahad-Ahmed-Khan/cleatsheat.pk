<?php

namespace App\Support\Bargain;

final class OfferExtractor
{
    /**
     * Best-effort extraction of a PKR amount from free text.
     * Returns a decimal string with 2 fractional digits, or null if not found.
     *
     * Order: currency-labelled comma forms → spaced thousands → k/K → standalone comma amounts → generic digit runs.
     */
    public static function extractPkrAmount(string $text): ?string
    {
        $t = $text;
        if ($t === '') {
            return null;
        }

        $commaNum = '(\d{1,3}(?:,\d{3})+|\d+)(?:\.\d{1,2})?';

        $currencyPatterns = [
            '/PKR\s*'.$commaNum.'\s*(k)?\b/iu',
            '/Rs\.?\s*'.$commaNum.'\s*(k)?\b/iu',
            '/\b'.$commaNum.'\s*(k)?\s*(?:PKR|Rs\.?)\b/iu',
        ];

        foreach ($currencyPatterns as $pattern) {
            $out = self::matchAndNormalize($pattern, $t, true);
            if ($out !== null) {
                return $out;
            }
        }

        if (preg_match('/\b(\d{1,3})\s+(\d{3})\b/', $t, $m) === 1) {
            $concat = $m[1].$m[2];
            if (is_numeric($concat)) {
                return number_format((float) $concat, 2, '.', '');
            }
        }

        if (preg_match('/\b(\d+(?:\.\d+)?)\s*[kK]\b/', $t, $m) === 1) {
            $raw = (string) $m[1];
            if (is_numeric($raw)) {
                return number_format(((float) $raw) * 1000.0, 2, '.', '');
            }
        }

        if (preg_match('/\b(\d{1,3}(?:,\d{3})+)(?:\.\d{1,2})?\b/', $t, $m) === 1) {
            $raw = str_replace(',', '', (string) $m[1]);
            if (is_numeric($raw)) {
                return number_format((float) $raw, 2, '.', '');
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

    /**
     * @param  non-empty-string  $pattern
     */
    private static function matchAndNormalize(string $pattern, string $text, bool $allowK): ?string
    {
        if (preg_match($pattern, $text, $m) !== 1) {
            return null;
        }

        $raw = str_replace(',', '', (string) $m[1]);
        if (! is_numeric($raw)) {
            return null;
        }

        $n = (float) $raw;
        $hasK = $allowK && isset($m[2]) && is_string($m[2]) && $m[2] !== '';
        if ($hasK) {
            $n *= 1000.0;
        }

        return number_format($n, 2, '.', '');
    }
}
