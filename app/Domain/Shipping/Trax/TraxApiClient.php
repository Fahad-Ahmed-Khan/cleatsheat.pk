<?php

namespace App\Domain\Shipping\Trax;

use App\Models\CourierAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TraxApiClient
{
    private const WORKING_BASE_TTL_DAYS = 7;

    public static function resolvedBaseUrl(?CourierAccount $account): string
    {
        $creds = $account?->credentials ?? [];
        $env = strtolower(trim((string) ($creds['api_environment'] ?? 'testing')));
        $env = in_array($env, ['testing', 'live'], true) ? $env : 'testing';

        $key = $env === 'live'
            ? 'shipping.endpoints.trax.live'
            : 'shipping.endpoints.trax.testing';

        return rtrim((string) config($key), '/');
    }

    public static function workingBaseUrl(?CourierAccount $account): ?string
    {
        if ($account === null) {
            return null;
        }

        $cached = Cache::get(self::workingBaseCacheKey($account));

        return is_string($cached) && $cached !== '' ? $cached : null;
    }

    /**
     * @return list<string>
     */
    public static function baseUrlCandidates(?CourierAccount $account): array
    {
        $primary = self::resolvedBaseUrl($account);
        $candidates = [];

        $working = self::workingBaseUrl($account);
        if ($working !== null) {
            $candidates[] = $working;
        }

        if (! in_array($primary, $candidates, true)) {
            $candidates[] = $primary;
        }

        $fallback = self::liveFallbackUrl($account);
        if ($fallback !== null && ! in_array($fallback, $candidates, true)) {
            $candidates[] = $fallback;
        }

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{response: Response, base: string, url: string}
     */
    public static function get(?CourierAccount $account, string $token, string $path, array $query = [], bool $probe = false): array
    {
        return self::send($account, $token, 'get', $path, $query, [], $probe);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{response: Response, base: string, url: string}
     */
    public static function post(?CourierAccount $account, string $token, string $path, array $payload = [], bool $probe = false): array
    {
        return self::send($account, $token, 'post', $path, [], $payload, $probe);
    }

    public static function request(string $token): PendingRequest
    {
        return self::baseRequest($token)
            ->retry(3, 500, null, false)
            ->connectTimeout(25)
            ->timeout(60);
    }

    /**
     * Short timeouts for CLI probes — fail fast when outbound HTTPS to Trax is blocked.
     */
    public static function probeRequest(string $token): PendingRequest
    {
        return self::baseRequest($token)
            ->connectTimeout(8)
            ->timeout(15);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $payload
     * @return array{response: Response, base: string, url: string}
     */
    private static function send(
        ?CourierAccount $account,
        string $token,
        string $method,
        string $path,
        array $query,
        array $payload,
        bool $probe,
    ): array {
        $path = '/'.ltrim($path, '/');
        $lastException = null;

        foreach (self::baseUrlCandidates($account) as $base) {
            $url = $base.$path;

            try {
                $client = $probe ? self::probeRequest($token) : self::request($token);
                $response = match ($method) {
                    'get' => $client->get($url, $query),
                    'post' => $client->post($url, $payload),
                    default => throw new \InvalidArgumentException("Unsupported HTTP method [{$method}]."),
                };

                self::rememberWorkingBaseUrl($account, $base);

                return ['response' => $response, 'base' => $base, 'url' => $url];
            } catch (ConnectionException $e) {
                Log::warning('trax.connection_failed', [
                    'url' => $url,
                    'account_id' => $account?->id,
                    'error' => $e->getMessage(),
                ]);
                $lastException = $e;
            }
        }

        if ($lastException !== null) {
            throw $lastException;
        }

        throw new ConnectionException('Trax API unreachable.');
    }

    private static function rememberWorkingBaseUrl(?CourierAccount $account, string $base): void
    {
        if ($account === null) {
            return;
        }

        Cache::put(
            self::workingBaseCacheKey($account),
            $base,
            now()->addDays(self::WORKING_BASE_TTL_DAYS),
        );
    }

    private static function workingBaseCacheKey(CourierAccount $account): string
    {
        $env = strtolower(trim((string) (($account->credentials ?? [])['api_environment'] ?? 'testing')));

        return 'trax:working_base:'.$account->id.':'.$env;
    }

    private static function liveFallbackUrl(?CourierAccount $account): ?string
    {
        if ($account === null) {
            return null;
        }

        $env = strtolower(trim((string) (($account->credentials ?? [])['api_environment'] ?? 'testing')));
        if ($env !== 'live') {
            return null;
        }

        $fallback = rtrim((string) config('shipping.endpoints.trax.live_fallback', ''), '/');
        if ($fallback === '') {
            return null;
        }

        $primary = self::resolvedBaseUrl($account);

        return $fallback === $primary ? null : $fallback;
    }

    private static function baseRequest(string $token): PendingRequest
    {
        $trimmed = trim($token);
        // Sonic docs say: Authorization: <API_KEY>. If their gateway expects Bearer,
        // allow storing "Bearer ..." directly in the DB; we won't override it.
        $headerValue = str_contains($trimmed, ' ') ? $trimmed : $trimmed;

        return Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'Authorization' => $headerValue,
            ]);
    }
}
