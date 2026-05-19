<?php

namespace App\Jobs;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Periodically sweep shipments that ended in the failed state and re-queue
 * BookShipmentJob for them up to a small bounded number of automatic retries.
 *
 * The retry counter is tracked inside the shipment.meta blob so we don't need
 * a schema change. Manual reset = just clear `meta.booking_retries`.
 */
class ReconcileFailedBookingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const MAX_AUTO_RETRIES = 3;

    public function __construct(
        public int $maxBatch = 25,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $shipments = Shipment::query()
            ->where('status', ShipmentStatus::Failed)
            ->where('failed_at', '<=', now()->subMinutes(15))
            ->orderBy('failed_at')
            ->limit($this->maxBatch)
            ->get();

        $requeued = 0;
        foreach ($shipments as $shipment) {
            $meta = $shipment->meta ?? [];
            $tries = (int) ($meta['booking_retries'] ?? 0);
            if ($tries >= self::MAX_AUTO_RETRIES) {
                continue;
            }

            $meta['booking_retries'] = $tries + 1;
            $meta['last_auto_retry_at'] = now()->toIso8601String();
            $shipment->meta = $meta;
            $shipment->status = ShipmentStatus::Pending;
            $shipment->failed_at = null;
            $shipment->save();

            BookShipmentJob::dispatch($shipment->id);
            $requeued++;
        }

        if ($requeued > 0) {
            Log::info('shipping.failed_bookings_requeued', [
                'count' => $requeued,
            ]);
        }
    }
}
