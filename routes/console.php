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

Schedule::command('shipping:sync-tracking')->everyThirtyMinutes();

// Audit follow-up reconciliation jobs.
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

// Daily WhatsApp pickup notices to courier riders. Runs hourly and checks the
// admin-configured pickup_notice_time so admins can change it without editing
// the schedule.
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
