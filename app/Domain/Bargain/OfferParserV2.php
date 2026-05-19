<?php

namespace App\Domain\Bargain;

use App\Support\Bargain\OfferExtractor;

final class OfferParserV2
{
    /**
     * @return array{
     *   singleAmountPkr?: string,
     *   rangeMinPkr?: string,
     *   rangeMaxPkr?: string,
     *   rawMatches: list<string>,
     * }
     */
    public function parse(string $message): array
    {
        $t = mb_strtolower(trim($message));
        if ($t === '') {
            return ['rawMatches' => []];
        }

        $raw = [];

        // Normalize separators: 10k sy 11k, 10 to 11k, 10-11k, 10k-11k
        $rangeRe = '/\b(\d+(?:\.\d+)?)\s*(k)?\s*(?:-|to|–|—|sy|se|tak|upto|up\s*to)\s*(\d+(?:\.\d+)?)\s*(k)?\b/iu';
        if (preg_match($rangeRe, $t, $m) === 1) {
            $a = $this->toPkr($m[1], ($m[2] ?? '') !== '');
            $b = $this->toPkr($m[3], ($m[4] ?? '') !== '');
            if ($a !== null && $b !== null) {
                $raw[] = (string) $m[0];
                $min = (float) $a <= (float) $b ? $a : $b;
                $max = (float) $a <= (float) $b ? $b : $a;

                return $this->safeOut(['rangeMinPkr' => $min, 'rangeMaxPkr' => $max, 'rawMatches' => $raw]);
            }
        }

        // Urdu-ish thousands: "11 hazar", "gyarah hazar"
        $hazarRe = '/\b([a-z]+|\d{1,2})\s*(?:hazar|hazaar|hzaar)\b/iu';
        if (preg_match($hazarRe, $t, $m) === 1) {
            $n = $this->urduOrDigitsToInt((string) $m[1]);
            if ($n !== null) {
                $raw[] = (string) $m[0];
                $pkr = number_format(((float) $n) * 1000.0, 2, '.', '');

                return $this->safeOut(['singleAmountPkr' => $pkr, 'rawMatches' => $raw]);
            }
        }

        // k/K single.
        if (preg_match('/\b(\d+(?:\.\d+)?)\s*[kK]\b/', $t, $m) === 1) {
            $raw[] = (string) $m[0];
            $p = $this->toPkr((string) $m[1], true);
            if ($p !== null) {
                return $this->safeOut(['singleAmountPkr' => $p, 'rawMatches' => $raw]);
            }
        }

        // Fallback to legacy extractor for comma / PKR / Rs.
        $legacy = OfferExtractor::extractPkrAmount($message);
        if (is_string($legacy) && $legacy !== '') {
            $raw[] = 'legacy';

            return $this->safeOut(['singleAmountPkr' => $legacy, 'rawMatches' => $raw]);
        }

        return ['rawMatches' => []];
    }

    /**
     * @param  array<string, mixed>  $out
     * @return array{singleAmountPkr?: string, rangeMinPkr?: string, rangeMaxPkr?: string, rawMatches: list<string>}
     */
    private function safeOut(array $out): array
    {
        // Hard caps to prevent catastrophic parsing. PKR 100 .. 5,000,000 only.
        foreach (['singleAmountPkr', 'rangeMinPkr', 'rangeMaxPkr'] as $k) {
            if (! array_key_exists($k, $out)) {
                continue;
            }
            $v = $out[$k];
            if (! is_string($v) || $v === '' || ! is_numeric($v)) {
                unset($out[$k]);

                continue;
            }
            $f = (float) $v;
            if ($f < 100.0 || $f > 5000000.0) {
                unset($out[$k]);
            } else {
                $out[$k] = number_format($f, 2, '.', '');
            }
        }

        /** @var list<string> $rm */
        $rm = $out['rawMatches'] ?? [];
        $out['rawMatches'] = $rm;

        return $out;
    }

    private function toPkr(string $rawNum, bool $isK): ?string
    {
        if (! is_numeric($rawNum)) {
            return null;
        }
        $n = (float) $rawNum;
        if ($isK) {
            $n *= 1000.0;
        }

        return number_format($n, 2, '.', '');
    }

    private function urduOrDigitsToInt(string $token): ?int
    {
        $token = mb_strtolower(trim($token));
        if ($token === '') {
            return null;
        }
        if (ctype_digit($token)) {
            $n = (int) $token;
            if ($n >= 1 && $n <= 99) {
                return $n;
            }

            return null;
        }

        // Minimal roman-urdu number words for 1–20 + a few common ones; extend as needed.
        $map = [
            'ek' => 1,
            'do' => 2,
            'teen' => 3,
            'char' => 4,
            'paanch' => 5,
            'che' => 6,
            'saat' => 7,
            'aath' => 8,
            'nau' => 9,
            'das' => 10,
            'gyarah' => 11,
            'bara' => 12,
            'barah' => 12,
            'terah' => 13,
            'chaudah' => 14,
            'pandrah' => 15,
            'solah' => 16,
            'satrah' => 17,
            'atharah' => 18,
            'unnis' => 19,
            'bees' => 20,
        ];

        return $map[$token] ?? null;
    }
}
