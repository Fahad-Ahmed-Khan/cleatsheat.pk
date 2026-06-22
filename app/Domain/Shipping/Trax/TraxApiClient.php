<?php

namespace App\Domain\Shipping\Trax;

use App\Models\CourierAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class TraxApiClient
{
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
