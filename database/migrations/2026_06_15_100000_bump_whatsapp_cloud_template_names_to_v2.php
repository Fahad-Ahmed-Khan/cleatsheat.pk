<?php

use App\Domain\Notifications\WhatsApp\WhatsAppTemplateSyncService;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        WhatsAppTemplate::query()
            ->where('has_buttons', false)
            ->orderBy('key')
            ->each(function (WhatsAppTemplate $template): void {
                $key = (string) $template->key;
                $current = trim((string) ($template->cloud_template_name ?? ''));

                if ($current !== '' && $current !== $key) {
                    return;
                }

                $template->cloud_template_name = WhatsAppTemplateSyncService::defaultMetaNameForKey($key);
                $template->save();
            });
    }

    public function down(): void
    {
        WhatsAppTemplate::query()
            ->where('has_buttons', false)
            ->orderBy('key')
            ->each(function (WhatsAppTemplate $template): void {
                $key = (string) $template->key;
                $expected = WhatsAppTemplateSyncService::defaultMetaNameForKey($key);

                if (trim((string) ($template->cloud_template_name ?? '')) !== $expected) {
                    return;
                }

                $template->cloud_template_name = null;
                $template->save();
            });
    }
};
