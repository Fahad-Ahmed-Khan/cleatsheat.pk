<?php

namespace App\Domain\Payments\Safepay;

use RuntimeException;
use Safepay\SafepayClient;

/**
 * Builds a {@see SafepayClient} configured for the currently active environment.
 *
 * Safepay exposes three base URLs (development / sandbox / production). The factory keeps
 * environment-specific knowledge in one place so the gateway and webhook handler can
 * obtain a ready-to-use client without duplicating config plumbing.
 */
class SafepayClientFactory
{
    private const API_BASES = [
        'development' => 'https://dev.api.getsafepay.com',
        'sandbox' => 'https://sandbox.api.getsafepay.com',
        'production' => 'https://api.getsafepay.com',
    ];

    public function make(): SafepayClient
    {
        $apiKey = (string) config('payments.gateways.safepay.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('Safepay API key is not configured.');
        }

        return new SafepayClient([
            'api_key' => $apiKey,
            'api_base' => $this->apiBase(),
        ]);
    }

    public function environment(): string
    {
        $env = strtolower((string) config('payments.gateways.safepay.environment', 'sandbox'));

        return array_key_exists($env, self::API_BASES) ? $env : 'sandbox';
    }

    public function apiBase(): string
    {
        return self::API_BASES[$this->environment()];
    }
}
