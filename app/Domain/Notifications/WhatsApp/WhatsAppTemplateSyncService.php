<?php

namespace App\Domain\Notifications\WhatsApp;

use App\Models\WhatsAppTemplate;
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

        $existing = $this->client->findMessageTemplate($metaName, $language);
        $existingBody = $existing !== null ? $this->extractBodyText($existing) : null;

        if ($existing !== null && $existingBody === $converted['body'] && ! $force) {
            return $this->markSynced($template, $metaName, $converted['parameter_order'], 'unchanged', 'Already in sync with Meta.');
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
            'components' => [
                [
                    'type' => 'BODY',
                    'text' => $converted['body'],
                    'example' => [
                        'body_text' => [$converted['examples']],
                    ],
                ],
            ],
        ];

        try {
            $response = $this->client->createMessageTemplate($payload);
        } catch (\Throwable $e) {
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
     * @param  array<string, mixed>  $metaTemplate
     */
    private function extractBodyText(array $metaTemplate): ?string
    {
        $components = is_array($metaTemplate['components'] ?? null) ? $metaTemplate['components'] : [];

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }
            if (strtoupper((string) ($component['type'] ?? '')) === 'BODY') {
                $text = trim((string) ($component['text'] ?? ''));

                return $text !== '' ? $text : null;
            }
        }

        return null;
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
