<?php

namespace App\Domain\Shipping\Trax;

use App\Models\CourierAccount;
use Illuminate\Support\Facades\Cache;

final class TraxCityResolver
{
    /**
     * @return array<int, array{id:int,name:string}>
     */
    public static function cities(CourierAccount $account, string $token): array
    {
        $base = TraxApiClient::resolvedBaseUrl($account);
        $cacheKey = 'trax:cities:'.$account->id.':'.md5($base);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            /** @var array<int, array{id:int,name:string}> $cached */
            return $cached;
        }

        $url = $base.'/api/cities';
        $res = TraxApiClient::request($token)->get($url);
        if (! $res->successful()) {
            // Do not cache failures (bad token / blocked / downtime) — that would break
            // bookings for up to 24 hours even after credentials are fixed.
            return [];
        }

        $body = $res->json();
        if (! is_array($body)) {
            return [];
        }

        $rows = $body['cities'] ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            if (! is_array($r)) {
                continue;
            }
            $id = $r['id'] ?? null;
            $name = $r['name'] ?? null;
            if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
                continue;
            }
            $id = (int) $id;
            $name = trim((string) $name);
            if ($id <= 0 || $name === '') {
                continue;
            }
            $out[] = ['id' => $id, 'name' => $name];
        }

        Cache::put($cacheKey, $out, now()->addHours(24));

        return $out;
    }

    public static function resolveCityId(CourierAccount $account, string $token, string $cityName): ?int
    {
        $needle = self::normalize($cityName);
        if ($needle === '') {
            return null;
        }

        $aliases = [
            'khi' => 'karachi',
            'lhr' => 'lahore',
            'isb' => 'islamabad',
            'rwp' => 'rawalpindi',
            'fsd' => 'faisalabad',
        ];
        if (isset($aliases[$needle])) {
            $needle = $aliases[$needle];
        }

        foreach (self::cities($account, $token) as $c) {
            if (self::normalize($c['name']) === $needle) {
                return (int) $c['id'];
            }
        }

        // Fallback: try a loose match (e.g. "Karachi, Sindh" vs "Karachi").
        foreach (self::cities($account, $token) as $c) {
            $norm = self::normalize($c['name']);
            if ($norm !== '' && (str_contains($needle, $norm) || str_contains($norm, $needle))) {
                return (int) $c['id'];
            }
        }

        return null;
    }

    private static function normalize(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/i', ' ', $s) ?? $s;
        $s = preg_replace('/\\s+/', ' ', $s) ?? $s;

        return trim($s);
    }
}

