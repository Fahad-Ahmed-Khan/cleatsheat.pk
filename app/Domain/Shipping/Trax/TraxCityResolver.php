<?php

namespace App\Domain\Shipping\Trax;

use App\Models\CourierAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class TraxCityResolver
{
    private const CACHE_TTL_HOURS = 24;

    private const STALE_CACHE_DAYS = 7;

    /**
     * @return array<int, array{id:int,name:string}>
     */
    public static function cities(CourierAccount $account, string $token): array
    {
        foreach (TraxApiClient::baseUrlCandidates($account) as $base) {
            $cached = Cache::get(self::cacheKey($account, $base));
            if (is_array($cached)) {
                /** @var array<int, array{id:int,name:string}> $cached */
                return $cached;
            }
        }

        try {
            ['response' => $res, 'base' => $base] = TraxApiClient::get($account, $token, '/api/cities');
        } catch (ConnectionException $e) {
            Log::warning('trax.cities_connection_failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            foreach (TraxApiClient::baseUrlCandidates($account) as $candidate) {
                $stale = Cache::get(self::staleCacheKey($account, $candidate));
                if (is_array($stale) && $stale !== []) {
                    return $stale;
                }
            }

            return [];
        }

        if (! $res->successful()) {
            return [];
        }

        $out = self::parseCitiesResponse($res->json());
        if ($out === []) {
            return [];
        }

        $cacheKey = self::cacheKey($account, $base);
        $staleKey = self::staleCacheKey($account, $base);
        Cache::put($cacheKey, $out, now()->addHours(self::CACHE_TTL_HOURS));
        Cache::put($staleKey, $out, now()->addDays(self::STALE_CACHE_DAYS));

        return $out;
    }

    /**
     * @param  array<int, array{id:int,name:string}>  $rows
     */
    public static function seedCache(CourierAccount $account, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        foreach (TraxApiClient::baseUrlCandidates($account) as $candidate) {
            $cacheKey = self::cacheKey($account, $candidate);
            $staleKey = self::staleCacheKey($account, $candidate);
            Cache::put($cacheKey, $rows, now()->addHours(self::CACHE_TTL_HOURS));
            Cache::put($staleKey, $rows, now()->addDays(self::STALE_CACHE_DAYS));
        }

        return count($rows);
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

    /**
     * @return array<int, array{id:int,name:string}>
     */
    public static function parseCitiesResponse(mixed $body): array
    {
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

        return $out;
    }

    private static function cacheKey(CourierAccount $account, string $base): string
    {
        return 'trax:cities:'.$account->id.':'.md5($base);
    }

    private static function staleCacheKey(CourierAccount $account, string $base): string
    {
        return 'trax:cities:stale:'.$account->id.':'.md5($base);
    }

    private static function normalize(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/i', ' ', $s) ?? $s;
        $s = preg_replace('/\\s+/', ' ', $s) ?? $s;

        return trim($s);
    }
}
