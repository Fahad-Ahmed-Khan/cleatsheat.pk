<?php

namespace App\Jobs;

use App\Domain\Shipping\ShipmentBookingService;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BookShipmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $shipmentId,
    ) {
        $this->onQueue('default');
    }

    public function handle(ShipmentBookingService $booking): void
    {
        $shipment = Shipment::query()->find($this->shipmentId);
        if ($shipment === null) {
            return;
        }

        if ($shipment->status !== ShipmentStatus::Pending) {
            return;
        }

        try {
            $booking->book($shipment);
        } catch (\Throwable $e) {
            Log::warning('shipping.book_failed', [
                'shipment_id' => $this->shipmentId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
