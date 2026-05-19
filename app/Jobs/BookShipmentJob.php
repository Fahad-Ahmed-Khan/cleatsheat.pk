<?php

namespace App\Jobs;

use App\Domain\Shipping\ShipmentBookingService;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        } catch (Throwable $e) {
            Log::error('shipping.book_unexpected_exception', [
                'shipment_id' => $this->shipmentId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    /**
     * Persist a readable failure when the job exhausts retries (e.g. decrypt / DB errors).
     */
    public function failed(?Throwable $exception): void
    {
        if ($exception === null) {
            return;
        }

        $shipment = Shipment::query()->find($this->shipmentId);
        if ($shipment === null || $shipment->status !== ShipmentStatus::Pending) {
            return;
        }

        DB::transaction(function () use ($shipment, $exception): void {
            $shipment->status = ShipmentStatus::Failed;
            $shipment->failed_at = now();
            $shipment->last_booking_response = [
                'queue_job_failed' => true,
                'exception' => $exception::class,
            ];
            $shipment->meta = array_merge($shipment->meta ?? [], [
                'booking_error' => 'Booking job failed after retries: '.$exception->getMessage(),
            ]);
            $shipment->save();

            ShipmentEvent::query()->create([
                'shipment_id' => $shipment->id,
                'status' => ShipmentStatus::Failed->value,
                'description' => 'Booking job failed: '.$exception->getMessage(),
                'raw_payload' => ['exception' => $exception::class],
                'occurred_at' => now(),
            ]);
        });
    }
}
