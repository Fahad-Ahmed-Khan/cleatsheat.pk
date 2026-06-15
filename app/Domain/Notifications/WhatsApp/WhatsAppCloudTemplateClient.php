<?php

namespace App\Domain\Notifications\WhatsApp;

use Illuminate\Support\Facades\Cache;

class WhatsAppCloudTemplateClient
{
    /** @var list<array<string, mixed>>|null */
    private ?array $templateListCache = null;

    public function clearTemplateListCache(): void
    {
        $this->templateListCache = null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMessageTemplates(bool $refresh = false): array
    {
        if (! $refresh && $this->templateListCache !== null) {
            return $this->templateListCache;
        }

        $wabaId = $this->resolveWabaId();
        $version = $this->apiVersion();
        $token = $this->token();

        $templates = [];
        $url = "https://graph.facebook.com/{$version}/{$wabaId}/message_templates";
        $params = [
            'fields' => 'id,name,language,status,category,components',
            'limit' => 100,
        ];

        do {
            $response = MetaGraphTransport::get($url, $token, $params);

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

        $this->templateListCache = $templates;

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

    public function deleteMessageTemplate(string $name, ?string $templateId = null): void
    {
        $wabaId = $this->resolveWabaId();
        $version = $this->apiVersion();
        $token = $this->token();

        $query = $templateId !== null && $templateId !== ''
            ? ['hsm_id' => $templateId]
            : ['name' => strtolower($name)];

        MetaGraphTransport::delete("https://graph.facebook.com/{$version}/{$wabaId}/message_templates", $token, $query);

        $this->clearTemplateListCache();
    }

    public function waitUntilTemplateAbsent(string $name, string $language, int $maxAttempts = 10, int $sleepSeconds = 2): void
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $this->clearTemplateListCache();

            if ($this->findMessageTemplate($name, $language) === null) {
                return;
            }

            if ($attempt < $maxAttempts) {
                sleep($sleepSeconds);
            }
        }

        throw new \RuntimeException(
            'Meta template still exists after delete ('.strtolower($name).', '.$language.'). '
            .'Delete it in Meta Business Manager or wait a minute and retry.'
        );
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
        /** @var array<string, mixed> $json */
        $json = MetaGraphTransport::post(
            "https://graph.facebook.com/{$version}/{$wabaId}/message_templates",
            $token,
            $payload,
        );

        $this->clearTemplateListCache();

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

            $wabaId = $this->resolveWabaIdFromBusinesses($phoneNumberId)
                ?? $this->resolveWabaIdFromDebugToken();

            if ($wabaId === null || $wabaId === '') {
                throw new \RuntimeException(
                    'Could not resolve WhatsApp Business Account ID automatically. '
                    .'Set WHATSAPP_CLOUD_WABA_ID in .env (Meta Developer Console → WhatsApp → API Setup → WhatsApp Business Account ID), '
                    .'then run: php artisan config:clear'
                );
            }

            return $wabaId;
        });
    }

    private function resolveWabaIdFromBusinesses(string $phoneNumberId): ?string
    {
        try {
            /** @var array<string, mixed> $businesses */
            $businesses = $this->graphGet('me/businesses', ['fields' => 'id']);
        } catch (\Throwable) {
            return null;
        }

        $rows = is_array($businesses['data'] ?? null) ? $businesses['data'] : [];
        $candidateWabaIds = [];

        foreach ($rows as $business) {
            if (! is_array($business)) {
                continue;
            }

            $businessId = (string) ($business['id'] ?? '');
            if ($businessId === '') {
                continue;
            }

            foreach (['owned_whatsapp_business_accounts', 'client_whatsapp_business_accounts'] as $edge) {
                try {
                    /** @var array<string, mixed> $wabas */
                    $wabas = $this->graphGet("{$businessId}/{$edge}", ['fields' => 'id']);
                } catch (\Throwable) {
                    continue;
                }

                $wabaRows = is_array($wabas['data'] ?? null) ? $wabas['data'] : [];
                foreach ($wabaRows as $waba) {
                    if (! is_array($waba)) {
                        continue;
                    }

                    $wabaId = (string) ($waba['id'] ?? '');
                    if ($wabaId === '') {
                        continue;
                    }

                    $candidateWabaIds[] = $wabaId;

                    if ($phoneNumberId === '') {
                        continue;
                    }

                    try {
                        /** @var array<string, mixed> $phones */
                        $phones = $this->graphGet("{$wabaId}/phone_numbers", ['fields' => 'id']);
                    } catch (\Throwable) {
                        continue;
                    }

                    $phoneRows = is_array($phones['data'] ?? null) ? $phones['data'] : [];
                    foreach ($phoneRows as $phone) {
                        if (is_array($phone) && (string) ($phone['id'] ?? '') === $phoneNumberId) {
                            return $wabaId;
                        }
                    }
                }
            }
        }

        $unique = array_values(array_unique($candidateWabaIds));

        // Single WABA on the token — safe default when phone_number_id is not set or not listed.
        if (count($unique) === 1) {
            return $unique[0];
        }

        return null;
    }

    private function resolveWabaIdFromDebugToken(): ?string
    {
        try {
            /** @var array<string, mixed> $json */
            $json = $this->graphGet('debug_token', ['input_token' => $this->token()]);
        } catch (\Throwable) {
            return null;
        }

        $data = is_array($json['data'] ?? null) ? $json['data'] : [];
        $scopes = is_array($data['granular_scopes'] ?? null) ? $data['granular_scopes'] : [];

        foreach ($scopes as $scope) {
            if (! is_array($scope)) {
                continue;
            }

            $name = (string) ($scope['scope'] ?? '');
            if (! in_array($name, ['whatsapp_business_management', 'whatsapp_business_messaging'], true)) {
                continue;
            }

            $targetIds = is_array($scope['target_ids'] ?? null) ? $scope['target_ids'] : [];
            if (count($targetIds) === 1) {
                return (string) $targetIds[0];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function graphGet(string $path, array $query = []): array
    {
        $version = $this->apiVersion();

        /** @var array<string, mixed> $json */
        $json = MetaGraphTransport::get(
            "https://graph.facebook.com/{$version}/{$path}",
            $this->token(),
            $query,
        );

        return $json;
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
