<?php

namespace App\Domain\Notifications\WhatsApp;

use App\Models\WhatsAppTemplate;
use App\Support\Sentry\ExceptionLogging;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WhatsAppTemplateSyncService
{
    public function __construct(
        private readonly WhatsAppCloudTemplateClient $client,
    ) {}

    /**
     * @return array{ok: bool, action: string, message: string, meta_name?: string}
     */
    public function sync(WhatsAppTemplate $template, bool $force = false): array
    {
        $this->client->assertConfigured();

        if ((bool) $template->has_buttons) {
            return $this->markSkipped($template, 'Interactive button templates are sent as session messages, not Meta message templates.');
        }

        try {
            $converted = MetaTemplateBodyConverter::convert($template->body);
        } catch (\InvalidArgumentException $e) {
            return $this->markFailed($template, $e->getMessage());
        }

        $metaName = $this->resolveMetaName($template);
        $language = trim((string) ($template->cloud_template_language ?: 'en_US'));
        if ($language === '') {
            $language = 'en_US';
        }

        try {
            $components = $this->buildComponents($template, $converted);
        } catch (\InvalidArgumentException $e) {
            return $this->markFailed($template, $e->getMessage());
        }

        $existing = $this->client->findMessageTemplate($metaName, $language);

        if ($existing !== null && ! $force) {
            $existingComponents = is_array($existing['components'] ?? null) ? $existing['components'] : [];
            if ($this->componentsFingerprint($existingComponents) === $this->componentsFingerprint($components)) {
                return $this->markSynced($template, $metaName, $converted['parameter_order'], 'unchanged', 'Already in sync with Meta.');
            }
        }

        $wasUpdate = $existing !== null;

        if ($existing !== null) {
            $status = strtoupper((string) ($existing['status'] ?? ''));
            if ($status === 'APPROVED' && ! $force) {
                return $this->markFailed(
                    $template,
                    'Meta template is approved and differs from local copy. Re-run with --force to delete and recreate (requires Meta re-approval).',
                );
            }

            $this->client->deleteMessageTemplate($metaName);
        }

        $payload = [
            'name' => $metaName,
            'language' => $language,
            'category' => $this->mapCategory((string) $template->category),
            'components' => $components,
        ];

        try {
            $response = $this->client->createMessageTemplate($payload);
        } catch (\Throwable $e) {
            ExceptionLogging::report($e, 'whatsapp.template_sync_failed', ['template_key' => $template->key]);

            return $this->markFailed($template, $e->getMessage());
        }

        Log::info('whatsapp.template_synced', [
            'template_key' => $template->key,
            'meta_name' => $metaName,
            'response' => $response,
        ]);

        $action = $wasUpdate ? 'updated' : 'created';

        return $this->markSynced(
            $template,
            $metaName,
            $converted['parameter_order'],
            'pending_review',
            "Submitted to Meta ({$action}). Awaiting approval.",
            $action,
        );
    }

    /**
     * @return array{total: int, created: int, updated: int, unchanged: int, skipped: int, failed: int, errors: list<string>}
     */
    public function syncAll(bool $onlyActive = true, bool $force = false): array
    {
        $this->client->assertConfigured();
        $this->client->clearTemplateListCache();

        $query = WhatsAppTemplate::query()->orderBy('key');
        if ($onlyActive) {
            $query->where('is_active', true);
        }

        $summary = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        /** @var Collection<int, WhatsAppTemplate> $templates */
        $templates = $query->get();
        $summary['total'] = $templates->count();

        foreach ($templates as $template) {
            $result = $this->sync($template, $force);

            if ($result['action'] === 'skipped') {
                $summary['skipped']++;

                continue;
            }

            if (! $result['ok']) {
                $summary['failed']++;
                $summary['errors'][] = "{$template->key}: {$result['message']}";

                continue;
            }

            match ($result['action']) {
                'created' => $summary['created']++,
                'updated' => $summary['updated']++,
                'unchanged' => $summary['unchanged']++,
                default => null,
            };
        }

        return $summary;
    }

    public function resolveMetaName(WhatsAppTemplate $template): string
    {
        $raw = trim((string) ($template->cloud_template_name ?? ''));
        if ($raw === '') {
            $raw = (string) $template->key;
        }

        $sanitized = strtolower(preg_replace('/[^a-z0-9_]/', '_', strtolower($raw)) ?? '');
        $sanitized = trim($sanitized, '_');

        if ($sanitized === '') {
            throw new \InvalidArgumentException('Could not derive a valid Meta template name.');
        }

        return $sanitized;
    }

    /**
     * Build the full Meta components array: HEADER (text), BODY, FOOTER, BUTTONS (URL).
     *
     * @param  array{body: string, parameter_order: list<string>, examples: list<string>}  $converted
     * @return list<array<string, mixed>>
     */
    private function buildComponents(WhatsAppTemplate $template, array $converted): array
    {
        $components = [];

        $headerText = trim((string) ($template->header_text ?? ''));
        if ($headerText !== '') {
            if (preg_match('/\{[a-z_]+\}/i', $headerText)) {
                throw new \InvalidArgumentException('Header text must be static — placeholders are not supported in Meta template headers.');
            }
            $components[] = [
                'type' => 'HEADER',
                'format' => 'TEXT',
                'text' => mb_substr($headerText, 0, 60),
            ];
        }

        $bodyComponent = [
            'type' => 'BODY',
            'text' => $converted['body'],
        ];
        if ($converted['examples'] !== []) {
            $bodyComponent['example'] = ['body_text' => [$converted['examples']]];
        }
        $components[] = $bodyComponent;

        $footerText = trim((string) ($template->footer_text ?? ''));
        if ($footerText !== '') {
            $components[] = [
                'type' => 'FOOTER',
                'text' => mb_substr($footerText, 0, 60),
            ];
        }

        $buttons = $this->buildUrlButtons($template);
        if ($buttons !== []) {
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $buttons,
            ];
        }

        return $components;
    }

    /**
     * Convert stored URL buttons into Meta BUTTONS entries. A `{order_number}`
     * token becomes the dynamic URL suffix variable {{1}}.
     *
     * @return list<array<string, mixed>>
     */
    private function buildUrlButtons(WhatsAppTemplate $template): array
    {
        $raw = is_array($template->url_buttons) ? $template->url_buttons : [];
        $buttons = [];

        foreach (array_slice($raw, 0, 2) as $btn) {
            if (! is_array($btn)) {
                continue;
            }
            $text = trim((string) ($btn['text'] ?? ''));
            $url = trim((string) ($btn['url'] ?? ''));
            if ($text === '' || $url === '') {
                continue;
            }

            $button = [
                'type' => 'URL',
                'text' => mb_substr($text, 0, 25),
            ];

            if (str_contains($url, '{order_number}')) {
                $button['url'] = str_replace('{order_number}', '{{1}}', $url);
                $button['example'] = [str_replace('{order_number}', 'ORD-1001', $url)];
            } else {
                $button['url'] = $url;
            }

            $buttons[] = $button;
        }

        return $buttons;
    }

    /**
     * Normalized fingerprint of the user-visible parts of a components array
     * (works for both our payload shape and Meta's API response shape).
     *
     * @param  array<int, mixed>  $components
     */
    private function componentsFingerprint(array $components): string
    {
        $norm = ['header' => null, 'body' => null, 'footer' => null, 'buttons' => []];

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $type = strtoupper((string) ($component['type'] ?? ''));
            $text = trim((string) ($component['text'] ?? ''));

            if ($type === 'HEADER') {
                $norm['header'] = $text;
            } elseif ($type === 'BODY') {
                $norm['body'] = $text;
            } elseif ($type === 'FOOTER') {
                $norm['footer'] = $text;
            } elseif ($type === 'BUTTONS') {
                $rawButtons = is_array($component['buttons'] ?? null) ? $component['buttons'] : [];
                foreach ($rawButtons as $btn) {
                    if (! is_array($btn)) {
                        continue;
                    }
                    $norm['buttons'][] = [
                        'type' => strtoupper((string) ($btn['type'] ?? '')),
                        'text' => (string) ($btn['text'] ?? ''),
                        'url' => (string) ($btn['url'] ?? ''),
                    ];
                }
            }
        }

        return (string) json_encode($norm);
    }

    private function mapCategory(string $category): string
    {
        return match ($category) {
            'marketing' => 'MARKETING',
            default => 'UTILITY',
        };
    }

    /**
     * @param  list<string>  $parameterOrder
     * @return array{ok: bool, action: string, message: string, meta_name?: string}
     */
    private function markSynced(
        WhatsAppTemplate $template,
        string $metaName,
        array $parameterOrder,
        string $status,
        string $message,
        ?string $action = null,
    ): array {
        if (trim((string) ($template->cloud_template_name ?? '')) === '') {
            $template->cloud_template_name = $metaName;
        }

        $template->meta_parameter_order = $parameterOrder;
        $template->meta_sync_status = $status;
        $template->meta_sync_error = null;
        $template->meta_last_synced_at = now();
        $template->save();

        return [
            'ok' => true,
            'action' => $action ?? $status,
            'message' => $message,
            'meta_name' => $metaName,
        ];
    }

    /**
     * @return array{ok: bool, action: string, message: string}
     */
    private function markSkipped(WhatsAppTemplate $template, string $message): array
    {
        $template->meta_sync_status = 'skipped';
        $template->meta_sync_error = $message;
        $template->save();

        return ['ok' => false, 'action' => 'skipped', 'message' => $message];
    }

    /**
     * @return array{ok: bool, action: string, message: string}
     */
    private function markFailed(WhatsAppTemplate $template, string $message): array
    {
        $template->meta_sync_status = 'failed';
        $template->meta_sync_error = $message;
        $template->save();

        Log::warning('whatsapp.template_sync_failed', [
            'template_key' => $template->key,
            'error' => $message,
        ]);

        return ['ok' => false, 'action' => 'failed', 'message' => $message];
    }
}
