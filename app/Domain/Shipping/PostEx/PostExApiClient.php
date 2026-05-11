<?php

namespace App\Domain\Shipping\PostEx;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PostExApiClient
{
    public function __construct(
        private readonly string $baseUrl,
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    public static function fromConfig(): self
    {
        return new self((string) config('shipping.endpoints.postex'));
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

    private function requestJson(string $method, string $url, string $token, array $query = [], array $payload = []): array
    {
        $method = strtolower($method);

        $req = Http::retry(3, 250)
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

