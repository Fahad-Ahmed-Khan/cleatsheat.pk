<?php

namespace App\Domain\Notifications\WhatsApp;

/**
 * Single entry point for Meta Graph API HTTP — Guzzle (web/tests) or native streams (CLI on shared hosts).
 */
final class MetaGraphTransport
{
    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public static function get(string $url, string $token, array $query = []): array
    {
        if (self::usesNative()) {
            return MetaGraphNativeTransport::request('GET', $url, $token, null, $query);
        }

        /** @var array<string, mixed> $json */
        $json = MetaGraphHttp::client()
            ->withToken($token)
            ->get($url, $query)
            ->throw()
            ->json();

        return $json;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    public static function post(string $url, string $token, array $json): array
    {
        if (self::usesNative()) {
            return MetaGraphNativeTransport::request('POST', $url, $token, $json);
        }

        /** @var array<string, mixed> $response */
        $response = MetaGraphHttp::client()
            ->withToken($token)
            ->asJson()
            ->post($url, $json)
            ->throw()
            ->json();

        return $response;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function delete(string $url, string $token, array $query = []): void
    {
        if (self::usesNative()) {
            MetaGraphNativeTransport::request('DELETE', $url, $token, null, $query);

            return;
        }

        MetaGraphHttp::client()
            ->withToken($token)
            ->delete($url, $query)
            ->throw();
    }

    public static function usesNative(): bool
    {
        return MetaGraphHttp::resolvedHandler() === 'native' && MetaGraphNativeTransport::isAvailable();
    }
}
