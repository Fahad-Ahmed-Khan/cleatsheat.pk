<?php

namespace App\Domain\Notifications\WhatsApp;

class WhatsAppClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendCloudMessage(array $payload): array
    {
        $cfg = config('whatsapp.cloud');

        $token = (string) ($cfg['token'] ?? '');
        $phoneNumberId = (string) ($cfg['phone_number_id'] ?? '');
        $version = (string) ($cfg['api_version'] ?? 'v21.0');

        if ($token === '' || $phoneNumberId === '') {
            throw new \RuntimeException('WhatsApp Cloud API credentials are not configured.');
        }

        $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/messages";

        /** @var array<string, mixed> $json */
        $json = MetaGraphTransport::post($url, $token, $payload);

        return $json;
    }

    /**
     * Fallback bridge: expects {to, body, ...}.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendBridgeMessage(array $payload): array
    {
        $cfg = config('whatsapp.bridge');

        $token = (string) ($cfg['api_token'] ?? '');
        $url = (string) ($cfg['api_url'] ?? '');

        if ($url === '') {
            return [
                'stub' => true,
                'note' => 'No WHATSAPP_API_URL configured — message not sent (dev/stub mode).',
            ];
        }

        $timeout = (int) config('whatsapp.retry.timeout_seconds', 30);

        $req = Http::timeout($timeout)->retry(2, 250)->acceptJson()->asJson();
        if ($token !== '') {
            $req = $req->withToken($token);
        }

        /** @var array<string, mixed> $json */
        $json = $req->post($url, $payload)->throw()->json() ?? [];

        return $json;
    }
}

