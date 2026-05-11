<?php

namespace App\Jobs;

use App\Domain\Shipping\ShippingTrackingSyncService;
use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncShipmentTrackingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public int $shipmentId,
    ) {}

    public function handle(ShippingTrackingSyncService $sync): void
    {
        $shipment = Shipment::query()->find($this->shipmentId);
        if ($shipment === null) {
            return;
        }

        try {
            $sync->syncShipment($shipment);
        } catch (\Throwable $e) {
            Log::notice('shipping.track_sync_failed', [
                'shipment_id' => $this->shipmentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
