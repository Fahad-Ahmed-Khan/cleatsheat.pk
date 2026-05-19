<?php

namespace App\Domain\Shipping\PostEx;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PostExApiClient
{
    private readonly string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Host-only base URL for PostEx (e.g. https://api.postex.pk).
     * Strips accidental path suffixes if POSTEX_API_BASE was pasted from docs.
     */
    public static function resolvedBaseUrl(): string
    {
        return self::normalizeConfiguredBase((string) config('shipping.endpoints.postex'));
    }

    public static function fromConfig(): self
    {
        return new self(self::resolvedBaseUrl());
    }

    public static function normalizeConfiguredBase(string $url): string
    {
        $url = rtrim(trim($url), '/');
        if ($url === '') {
            return '';
        }

        $lower = strtolower($url);
        foreach ([
            '/services/integration/api/order',
            '/services/integration/api',
            '/services/integration',
        ] as $suffix) {
            $suffixLower = strtolower($suffix);
            if (strlen($lower) >= strlen($suffixLower) && str_ends_with($lower, $suffixLower)) {
                $url = rtrim(substr($url, 0, -strlen($suffix)), '/');
                $lower = strtolower($url);
            }
        }

        return $url;
    }

    /**
     * @return array{statusCode?: mixed, statusMessage?: mixed, dist?: mixed}
     */
    public function getOperationalCities(string $token, ?string $operationalCityType = null): array
    {
        $url = $this->baseUrl.'/services/integration/api/order/v2/get-operational-city';
        $query = [];
        if ($operationalCityType !== null && $operationalCityType !== '') {
            $query['operationalCityType'] = $operationalCityType;
        }

        return $this->requestJson('get', $url, $token, $query);
    }

    /**
     * @return array{statusCode?: mixed, statusMessage?: mixed, dist?: mixed}
     */
    public function getMerchantAddresses(string $token, ?string $cityName = null): array
    {
        $url = $this->baseUrl.'/services/integration/api/order/v1/get-merchant-address';
        $query = [];
        if ($cityName !== null && $cityName !== '') {
            $query['cityName'] = $cityName;
        }

        return $this->requestJson('get', $url, $token, $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{statusCode?: mixed, statusMessage?: mixed, dist?: mixed}
     */
    public function createMerchantAddress(string $token, array $payload): array
    {
        $url = $this->baseUrl.'/services/integration/api/order/v2/create-merchant-address';

        return $this->requestJson('post', $url, $token, [], $payload);
    }

    /**
     * @return array{statusCode?: mixed, statusMessage?: mixed, dist?: mixed}
     */
    public function trackOrder(string $token, string $trackingNumber): array
    {
        $url = $this->baseUrl.'/services/integration/api/order/v1/track-order/'.rawurlencode($trackingNumber);

        return $this->requestJson('get', $url, $token, []);
    }

    private function requestJson(string $method, string $url, string $token, array $query = [], array $payload = []): array
    {
        $method = strtolower($method);

        $req = Http::retry(3, 250, null, false)
            ->timeout(30)
            ->acceptJson()
            ->withHeaders(['token' => $token]);

        /** @var Response $res */
        $res = match ($method) {
            'post' => $req->asJson()->post($url, $payload),
            'put' => $req->asJson()->put($url, $payload),
            default => $req->get($url, $query),
        };

        return $res->json() ?: [];
    }
}
