<?php

namespace App\Console\Commands;

use App\Domain\Notifications\WhatsApp\WhatsAppTemplateSyncService;
use App\Models\WhatsAppTemplate;
use Illuminate\Console\Command;

class SyncWhatsAppTemplatesCommand extends Command
{
    protected $signature = 'whatsapp:sync-templates
                            {--template= : Sync a single template by key}
                            {--all : Sync all templates (default when no --template)}
                            {--inactive : Include inactive templates}
                            {--force : Delete and recreate approved Meta templates when body changed}';

    protected $description = 'Create or update Meta WhatsApp message templates from admin template definitions';

    public function handle(WhatsAppTemplateSyncService $sync): int
    {
        $key = trim((string) $this->option('template'));
        $force = (bool) $this->option('force');
        $onlyActive = ! (bool) $this->option('inactive');

        try {
            if ($key !== '') {
                $template = WhatsAppTemplate::query()->where('key', $key)->first();
                if ($template === null) {
                    $this->error("Template not found: {$key}");

                    return self::FAILURE;
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
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
