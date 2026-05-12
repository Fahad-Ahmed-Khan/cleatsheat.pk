<?php

namespace App\Domain\Bargain;

use App\Support\Bargain\OfferExtractor;

final class ConversationAnalyzer
{
    private const CASUAL_MARKERS = [
        'bhai', 'bhaijaan', 'yar', 'yaar', 'janab', 'scene', 'boss', 'sir',
    ];

    /**
     * @param  list<array{id?:int, role:string, body:string, meta?:array<string, mixed>}>  $messages  chronological
     */
    public function analyze(array $messages): ConversationSignals
    {
        $totalCustomer = 0;
        $totalAssistant = 0;
        $amounts = [];
        $lastCustomerBody = null;
        $casualFound = [];

        $assistantBodies = [];

        foreach ($messages as $m) {
            $role = (string) ($m['role'] ?? '');
            $body = (string) ($m['body'] ?? '');
            $meta = is_array($m['meta'] ?? null) ? $m['meta'] : [];

            if ($role === 'customer') {
                $totalCustomer++;
                $lastCustomerBody = $body;
                $parsed = $meta['parsed_offer'] ?? null;
                if (! is_string($parsed) || $parsed === '') {
                    $parsed = OfferExtractor::extractPkrAmount($body);
                }
                if (is_string($parsed) && $parsed !== '') {
                    $amounts[] = $parsed;
                }
                foreach (self::detectCasualMarkers($body) as $c) {
                    $casualFound[$c] = true;
                }
            } elseif ($role === 'assistant') {
                $totalAssistant++;
                if (trim($body) !== '') {
                    $assistantBodies[] = $body;
                }
            }
        }

        $streak = $this->sameOfferStreakAtEnd($amounts);
        [$progressing, $direction] = $this->progression($amounts);
        $momentum = $this->momentumScore($amounts, $progressing, $streak);
        $assistantRepeated = $this->assistantOpenerRepeated($assistantBodies);

        $lower = mb_strtolower($lastCustomerBody ?? '');
        $finalLast = (bool) (preg_match('/\b(final|last)\b/i', $lower) || preg_match('/\bdone\??\b/i', $lower));
        $ultraShort = $lastCustomerBody !== null && mb_strlen(trim($lastCustomerBody)) <= 22;

        return new ConversationSignals(
            totalCustomerMessages: $totalCustomer,
            totalAssistantMessages: $totalAssistant,
            customerMessagesWithParsedAmount: count($amounts),
            customerOfferAmountsChronological: $amounts,
            sameOfferStreakAtEnd: $streak,
            customerIsProgressing: $progressing,
            movementDirection: $direction,
            momentumScore: $momentum,
            casualMarkersFound: array_keys($casualFound),
            assistantOpenerLikelyRepeated: $assistantRepeated,
            customerUsedFinalOrLast: $finalLast,
            isUltraShortLastCustomer: $ultraShort,
            lastCustomerBodyRaw: $lastCustomerBody,
        );
    }

    /**
     * @return list<string>
     */
    private static function detectCasualMarkers(string $body): array
    {
        $t = mb_strtolower($body);
        $out = [];
        foreach (self::CASUAL_MARKERS as $w) {
            if (str_contains($t, $w)) {
                $out[] = $w;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $amounts
     */
    private function sameOfferStreakAtEnd(array $amounts): int
    {
        if ($amounts === []) {
            return 0;
        }
        $last = $amounts[array_key_last($amounts)];
        $streak = 0;
        for ($i = count($amounts) - 1; $i >= 0; $i--) {
            if ($amounts[$i] === $last) {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * @param  list<string>  $amounts
     * @return array{0: bool, 1: string}
     */
    private function progression(array $amounts): array
    {
        if (count($amounts) < 2) {
            return [false, 'unknown'];
        }
        $a = $amounts[count($amounts) - 2];
        $b = $amounts[count($amounts) - 1];
        $cmp = bccomp($b, $a, 2);
        if ($cmp === 1) {
            return [true, 'up'];
        }
        if ($cmp === -1) {
            return [false, 'down'];
        }

        return [false, 'flat'];
    }

    /**
     * @param  list<string>  $amounts
     */
    private function momentumScore(array $amounts, bool $progressing, int $streak): float
    {
        $base = 0.35;
        if ($progressing) {
            $base += 0.35;
        }
        $steps = max(0, count($amounts) - 1);
        $base += min(0.25, $steps * 0.06);
        if ($streak >= 2) {
            $base -= 0.25;
        }

        return max(0.0, min(1.0, $base));
    }

    /**
     * @param  list<string>  $assistantBodies  chronological
     */
    private function assistantOpenerRepeated(array $assistantBodies): bool
    {
        if (count($assistantBodies) < 2) {
            return false;
        }
        $n = count($assistantBodies);
        $a = $this->normalizeOpener($assistantBodies[$n - 2]);
        $b = $this->normalizeOpener($assistantBodies[$n - 1]);
        if ($a === '' || $b === '') {
            return false;
        }

        return $a === $b;
    }

    private function normalizeOpener(string $body): string
    {
        $first = explode("\n", trim($body), 2)[0];
        $norm = mb_strtolower(preg_replace('/\s+/u', ' ', $first) ?? '');

        return mb_strlen($norm) > 88 ? mb_substr($norm, 0, 88) : $norm;
    }
}
