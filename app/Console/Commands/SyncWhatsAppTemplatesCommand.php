<?php

namespace App\Console\Commands;

use App\Domain\Notifications\WhatsApp\MetaGraphHttp;
use App\Domain\Notifications\WhatsApp\MetaGraphNativeTransport;
use App\Domain\Notifications\WhatsApp\WhatsAppTemplateSyncService;
use App\Models\WhatsAppTemplate;
use App\Support\Sentry\ExceptionLogging;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class SyncWhatsAppTemplatesCommand extends Command
{
    protected $signature = 'whatsapp:sync-templates
                            {--template= : Sync a single template by key}
                            {--all : Sync all templates (default when no --template)}
                            {--inactive : Include inactive templates}
                            {--force : Delete and recreate approved Meta templates when body changed}
                            {--prepare-only : Build the Meta payload locally without calling Graph API}';

    protected $description = 'Create or update Meta WhatsApp message templates from admin template definitions';

    public function handle(WhatsAppTemplateSyncService $sync): int
    {
        $key = trim((string) $this->option('template'));
        $force = (bool) $this->option('force');
        $onlyActive = ! (bool) $this->option('inactive');

        if ($force && $key === '') {
            $this->warn('Force mode: syncing all templates. Prefer --template=key one at a time on shared hosting.');
        }

        $this->info('Starting Meta WhatsApp template sync...');
        $this->ensureCliSafeTransport();

        try {
            if ($key !== '') {
                $template = WhatsAppTemplate::query()->where('key', $key)->first();
                if ($template === null) {
                    $this->error("Template not found: {$key}");

                    return self::FAILURE;
                }

                if ((bool) $this->option('prepare-only')) {
                    $payload = $sync->buildMetaPayload($template);
                    unset($payload['parameter_order']);
                    $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
                    $this->info('Payload built locally (no Graph API call).');

                    return self::SUCCESS;
                }

                $result = $sync->sync($template, $force);
                $this->line("{$template->key}: {$result['message']}");

                return $result['ok'] || $result['action'] === 'skipped' ? self::SUCCESS : self::FAILURE;
            }

            $summary = $sync->syncAll($onlyActive, $force);

            $this->info("Processed {$summary['total']} template(s).");
            $this->line("Created: {$summary['created']}, updated: {$summary['updated']}, unchanged: {$summary['unchanged']}, skipped: {$summary['skipped']}, failed: {$summary['failed']}");

            foreach ($summary['errors'] as $error) {
                $this->warn($error);
            }

            return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $e) {
            ExceptionLogging::report($e, 'whatsapp.templates.sync_command_failed');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Hostinger/LiteSpeed PHP builds can segfault inside Guzzle/curl during artisan commands.
     */
    private function ensureCliSafeTransport(): void
    {
        if (PHP_SAPI !== 'cli') {
            return;
        }

        Config::set('whatsapp.http.handler', 'native');
        Config::set('whatsapp.http.curl_in_cli', false);

        $handler = MetaGraphHttp::resolvedHandler();
        if ($handler === 'native' && ! MetaGraphNativeTransport::isAvailable()) {
            $this->warn('Native Meta Graph transport unavailable (allow_url_fopen?). Falling back to stream.');
            Config::set('whatsapp.http.handler', 'stream');
            $handler = MetaGraphHttp::resolvedHandler();
        }

        $this->line('Meta Graph HTTP transport: '.$handler);
    }
}
