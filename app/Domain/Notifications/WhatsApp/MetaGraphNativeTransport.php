<?php

namespace App\Domain\Notifications\WhatsApp;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

/**
 * Meta Graph HTTP via PHP streams (file_get_contents) — no Guzzle/curl.
 *
 * Hostinger CLI can segfault inside both libcurl and Guzzle's StreamHandler;
 * this transport avoids those code paths when allow_url_fopen is enabled.
 */
final class MetaGraphNativeTransport
{
    public static function isAvailable(): bool
    {
        if (! function_exists('file_get_contents') || ! function_exists('stream_context_create')) {
            return false;
        }

        return filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL);
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @param  array<string, mixed>|null  $query
     * @return array<string, mixed>
     */
    public static function request(string $method, string $url, string $token, ?array $json = null, ?array $query = null): array
    {
        if (! self::isAvailable()) {
            throw new \RuntimeException('Native Meta Graph transport requires allow_url_fopen=1.');
        }

        if ($query !== null && $query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        $timeout = (int) config('whatsapp.retry.timeout_seconds', 30);
        $headers = [
            'Authorization: Bearer '.$token,
            'Accept: application/json',
            'User-Agent: tryino-ecom-meta-graph',
        ];

        $http = [
            'method' => strtoupper($method),
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers),
        ];

        if ($json !== null) {
            $encoded = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new \RuntimeException('Failed to encode Meta Graph API JSON body.');
            }
            $http['header'] .= "\r\nContent-Type: application/json";
            $http['content'] = $encoded;
        }

        $context = stream_context_create([
            'http' => $http,
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            $error = error_get_last();

            throw new \RuntimeException(
                'Meta Graph API request failed (native transport)'
                .(isset($error['message']) ? ': '.$error['message'] : '.'),
            );
        }

        $status = self::responseStatusCode();

        if ($status >= 400) {
            throw new RequestException(new Response(
                new \GuzzleHttp\Psr7\Response($status, [], $body),
            ));
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($body, true) ?? [];

        return $decoded;
    }

    private static function responseStatusCode(): int
    {
        $headers = $GLOBALS['http_response_header'] ?? [];
        $first = is_array($headers) ? ($headers[0] ?? '') : '';

        if (is_string($first) && preg_match('/\s(\d{3})\s/', $first, $matches)) {
            return (int) $matches[1];
        }

        return 200;
    }
}
