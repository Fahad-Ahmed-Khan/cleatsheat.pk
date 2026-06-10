<?php

namespace App\Domain\Notifications\WhatsApp;

use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for Meta Graph API calls.
 *
 * Shared-hosting PHP builds (e.g. Hostinger/LiteSpeed) can segfault inside libcurl
 * during artisan commands. CLI defaults to Guzzle's stream handler to avoid curl.
 */
final class MetaGraphHttp
{
    public static function client(): PendingRequest
    {
        $timeout = (int) config('whatsapp.retry.timeout_seconds', 30);
        $request = Http::timeout($timeout)->acceptJson();

        $handler = strtolower(trim((string) config('whatsapp.http.handler', '')));
        if ($handler === '') {
            $handler = PHP_SAPI === 'cli' ? 'stream' : 'curl';
        }

        if ($handler === 'stream') {
            $request = $request->withOptions([
                'handler' => HandlerStack::create(new StreamHandler()),
            ]);
        } else {
            $curl = [];
            if ((bool) config('whatsapp.http.force_ipv4', true) && defined('CURLOPT_IPRESOLVE')) {
                $curl[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
            }
            if ((bool) config('whatsapp.http.curl_http1', true) && defined('CURLOPT_HTTP_VERSION')) {
                $curl[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
            }
            if ($curl !== []) {
                $request = $request->withOptions(['curl' => $curl]);
            }
        }

        $retries = (int) config('whatsapp.http.retries', 2);
        if ($retries > 0) {
            $request = $request->retry($retries, 250);
        }

        return $request;
    }
}
