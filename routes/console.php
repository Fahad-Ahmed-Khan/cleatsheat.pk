<?php

use App\Jobs\ProcessNotificationRetryJob;
use App\Jobs\ReconcileCodFromCourierWebhookJob;
use App\Jobs\ReconcileFailedBookingsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('shipping:sync-tracking')->everyThirtyMinutes();

// Audit follow-up reconciliation jobs.
Schedule::job(new ReconcileFailedBookingsJob())
    ->everyFifteenMinutes()
    ->name('reconcile-failed-bookings')
    ->withoutOverlapping();

Schedule::job(new ProcessNotificationRetryJob())
    ->everyTenMinutes()
    ->name('process-notification-retries')
    ->withoutOverlapping();

Schedule::job(new ReconcileCodFromCourierWebhookJob())
    ->hourly()
    ->name('reconcile-cod-from-deliveries')
    ->withoutOverlapping();
