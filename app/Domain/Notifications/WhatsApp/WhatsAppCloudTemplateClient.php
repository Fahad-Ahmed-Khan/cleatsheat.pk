<?php

namespace App\Domain\Notifications\WhatsApp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WhatsAppCloudTemplateClient
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listMessageTemplates(): array
    {
        $wabaId = $this->resolveWabaId();
        $version = $this->apiVersion();
        $token = $this->token();
        $timeout = (int) config('whatsapp.retry.timeout_seconds', 30);

        $templates = [];
        $url = "https://graph.facebook.com/{$version}/{$wabaId}/message_templates";
        $params = [
            'fields' => 'id,name,language,status,category,components',
            'limit' => 100,
        ];

        do {
            $response = Http::timeout($timeout)
                ->retry(2, 250)
                ->withToken($token)
                ->acceptJson()
                ->get($url, $params)
                ->throw()
                ->json();

            $batch = is_array($response['data'] ?? null) ? $response['data'] : [];
            foreach ($batch as $row) {
                if (is_array($row)) {
                    $templates[] = $row;
                }
            }

            $next = $response['paging']['next'] ?? null;
            if (! is_string($next) || $next === '') {
                break;
            }

            $url = $next;
            $params = [];
        } while (true);

        return $templates;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findMessageTemplate(string $name, string $language): ?array
    {
        $name = strtolower($name);

        foreach ($this->listMessageTemplates() as $template) {
            if (
                strtolower((string) ($template['name'] ?? '')) === $name
                && (string) ($template['language'] ?? '') === $language
            ) {
                return $template;
            }
        }

        return null;
    }

    public function deleteMessageTemplate(string $name): void
    {
        $wabaId = $this->resolveWabaId();
        $version = $this->apiVersion();
        $token = $this->token();
        $timeout = (int) config('whatsapp.retry.timeout_seconds', 30);

        Http::timeout($timeout)
            ->retry(2, 250)
            ->withToken($token)
            ->acceptJson()
            ->delete("https://graph.facebook.com/{$version}/{$wabaId}/message_templates", [
                'name' => strtolower($name),
            ])
            ->throw();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createMessageTemplate(array $payload): array
    {
        $wabaId = $this->resolveWabaId();
        $version = $this->apiVersion();
        $token = $this->token();
        $timeout = (int) config('whatsapp.retry.timeout_seconds', 30);

        /** @var array<string, mixed> $json */
        $json = Http::timeout($timeout)
            ->retry(2, 250)
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->post("https://graph.facebook.com/{$version}/{$wabaId}/message_templates", $payload)
            ->throw()
            ->json();

        return $json;
    }

    public function resolveWabaId(): string
    {
        $configured = trim((string) config('whatsapp.cloud.waba_id', ''));
        if ($configured !== '') {
            return $configured;
        }

        return Cache::remember('whatsapp.cloud.waba_id', now()->addDay(), function (): string {
            $phoneNumberId = trim((string) config('whatsapp.cloud.phone_number_id', ''));
            $token = $this->token();
            $version = $this->apiVersion();

            if ($phoneNumberId === '') {
                throw new \RuntimeException('WHATSAPP_CLOUD_PHONE_NUMBER_ID is not configured.');
            }

            /** @var array<string, mixed> $json */
            $json = Http::timeout((int) config('whatsapp.retry.timeout_seconds', 30))
                ->withToken($token)
                ->acceptJson()
                ->get("https://graph.facebook.com/{$version}/{$phoneNumberId}", [
                    'fields' => 'whatsapp_business_account',
                ])
                ->throw()
                ->json();

            $waba = $json['whatsapp_business_account'] ?? null;
            $id = is_array($waba) ? (string) ($waba['id'] ?? '') : '';

            if ($id === '') {
                throw new \RuntimeException('Could not resolve WhatsApp Business Account ID from phone number.');
            }

            return $id;
        });
    }

    public function assertConfigured(): void
    {
        if (! (bool) config('whatsapp.cloud.enabled', false)) {
            throw new \RuntimeException('WhatsApp Cloud API is disabled. Set WHATSAPP_CLOUD_ENABLED=true.');
        }

        if ($this->token() === '') {
            throw new \RuntimeException('WHATSAPP_CLOUD_TOKEN is not configured.');
        }
    }

    private function token(): string
    {
        return trim((string) config('whatsapp.cloud.token', ''));
    }

    private function apiVersion(): string
    {
        return (string) config('whatsapp.cloud.api_version', 'v21.0');
    }
}
