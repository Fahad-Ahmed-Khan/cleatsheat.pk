<?php

namespace App\Jobs;

use App\Domain\Shipping\Pickup\PickupDispatchService;
use App\Models\WhatsAppSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Scheduled wrapper that fires daily pickup notices once per courier per day.
 *
 * The schedule in routes/console.php runs this hourly; the job then checks the
 * admin-configured `pickup_notice_time` and only dispatches when the current
 * hour:minute equals (or has just passed) that target. PickupDispatchService
 * is itself idempotent on (courier_id, date, sent_via=auto), so repeated runs
 * within the same day are safe even if the scheduler executes us twice.
 */
class DispatchDailyPickupNoticesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(PickupDispatchService $service): void
    {
        $settings = WhatsAppSetting::current();
        if (! $settings->enabled_pickup_notices) {
            return;
        }

        $now = Carbon::now();
        $target = (string) ($settings->pickup_notice_time ?? '11:00');
        $parts = explode(':', $target);
        $hour = (int) ($parts[0] ?? 11);
        $minute = (int) ($parts[1] ?? 0);

        // Only run when we are at or past the target hour AND minute is within
        // the same hour as the target. This prevents earlier-in-the-day runs
        // from firing while still being robust if the scheduler misses the
        // exact minute.
        if ($now->hour !== $hour) {
            return;
        }
        if ($now->minute < $minute) {
            return;
        }

        $service->dispatchAllDueToday($now, sentVia: 'auto');
    }
}
