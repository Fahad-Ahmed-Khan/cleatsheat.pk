<?php

use App\Domain\Marketing\WhatsAppCampaignSender;
use App\Jobs\DispatchDailyPickupNoticesJob;
use App\Jobs\ProcessNotificationRetryJob;
use App\Jobs\ReconcileCodFromCourierWebhookJob;
use App\Jobs\ReconcileFailedBookingsJob;
use App\Models\WhatsAppCampaign;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Hostinger cron (required once)
|--------------------------------------------------------------------------
|
|   * * * * * cd /path/to/tryino-ecom && php artisan schedule:run >> /dev/null 2>&1
|
| Everything below is picked up by schedule:run. Do NOT schedule these
| artisan commands separately in cron.
|
| Manual / deploy-only (NOT in scheduler):
|   deploy:notify              — called from scripts/hostinger/deploy.sh
|   catalog:rebuild-search-index (full) — deploy.sh + manual recovery
|   catalog:seed-football-demo — dev seeding only
|   bargain:test-polisher      — dev diagnostic
|   postex:probe               — integration diagnostic
|   storage:normalize-paths    — one-off data migration utility
|
|--------------------------------------------------------------------------
*/

// --- Deploy (webhook sets a pending marker; cron runs the shell script) --------
Schedule::command('deploy:run-pending')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// --- Queue worker (database driver; no Supervisor on shared hosting) -----------
// Drains jobs dispatched by webhooks, shipping sync, WhatsApp, payments, etc.
// Scheduled jobs below also enqueue work — this must run every minute.
Schedule::command('queue:work database --stop-when-empty --max-time=55 --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('queue:prune-failed --hours=168')
    ->weeklyOn(1, '04:00')
    ->runInBackground();

// --- Shipping ----------------------------------------------------------------
Schedule::command('shipping:sync-tracking')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// --- Catalog / storefront maintenance (idempotent; skip when nothing to do) ---
Schedule::command('catalog:rebuild-search-index --missing')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('products:generate-image-variants')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('storefront:generate-hero-variants')
    ->dailyAt('04:15')
    ->withoutOverlapping()
    ->runInBackground();

// --- Order / payment / notification reconciliation ---------------------------
Schedule::job(new ReconcileFailedBookingsJob)
    ->everyFifteenMinutes()
    ->name('reconcile-failed-bookings')
    ->withoutOverlapping();

Schedule::job(new ProcessNotificationRetryJob)
    ->everyTenMinutes()
    ->name('process-notification-retries')
    ->withoutOverlapping();

Schedule::job(new ReconcileCodFromCourierWebhookJob)
    ->hourly()
    ->name('reconcile-cod-from-deliveries')
    ->withoutOverlapping();

// --- WhatsApp ----------------------------------------------------------------
// Pickup notices: hourly check against admin-configured pickup_notice_time.
Schedule::job(new DispatchDailyPickupNoticesJob)
    ->hourly()
    ->name('dispatch-daily-pickup-notices')
    ->withoutOverlapping();

Schedule::call(function (): void {
    $sender = app(WhatsAppCampaignSender::class);
    WhatsAppCampaign::query()
        ->where('status', 'scheduled')
        ->where('scheduled_for', '<=', now())
        ->orderBy('id')
        ->each(function (WhatsAppCampaign $campaign) use ($sender): void {
            $sender->prepareAndQueue($campaign);
        });
})->everyMinute()->name('whatsapp-campaigns-scheduled');
