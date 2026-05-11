<?php

namespace App\Domain\Shipping;

use App\Enums\ShipmentStatus;
use App\Models\Courier;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use Illuminate\Support\Facades\DB;

class ShipmentBookingService
{
    public function __construct(
        private readonly CourierRegistry $registry,
    ) {}

    /**
     * Books shipment with the courier adapter (queued callers should wrap failures).
     */
    public function book(Shipment $shipment): void
    {
        $shipment->load(['order', 'courier', 'courierAccount']);

        $courier = $shipment->courier;
        if ($courier === null) {
            throw new \InvalidArgumentException('Assign a courier before booking.');
        }

        $adapter = $this->registry->forCourier($courier);

        if ($adapter->code() !== 'generic' && $shipment->courier_account_id === null) {
            throw new \InvalidArgumentException('Configure an active API account for '.$courier->name.' before booking.');
        }

        $result = $adapter->book($shipment, $courier, $shipment->courierAccount);

        DB::transaction(function () use ($shipment, $result, $courier): void {
            $shipment->last_booking_response = $result->raw;

            if (! $result->success) {
                $shipment->status = ShipmentStatus::Failed;
                $shipment->failed_at = now();
                $shipment->meta = array_merge($shipment->meta ?? [], [
                    'booking_error' => $result->errorMessage,
                ]);
                $shipment->save();

                ShipmentEvent::query()->create([
                    'shipment_id' => $shipment->id,
                    'status' => ShipmentStatus::Failed->value,
                    'description' => $result->errorMessage ?? 'Booking failed',
                    'raw_payload' => $result->raw,
                    'occurred_at' => now(),
                ]);

                return;
            }

            $shipment->tracking_number = $result->trackingNumber ?? $shipment->tracking_number;
            $shipment->booking_reference = $result->bookingReference ?? $shipment->booking_reference;
            $shipment->label_url = $result->labelUrl ?? $shipment->label_url;
            $shipment->invoice_url = $result->invoiceUrl ?? $shipment->invoice_url;
            if ($result->shippingCharges !== null) {
                $shipment->shipping_charges = $result->shippingCharges;
            }
            $shipment->status = ShipmentStatus::Booked;
            $shipment->booked_at = now();
            $shipment->shipped_at = $shipment->shipped_at ?? now();
            $shipment->save();

            ShipmentEvent::query()->create([
                'shipment_id' => $shipment->id,
                'status' => ShipmentStatus::Booked->value,
                'description' => 'Shipment booked with '.$courier->name,
                'raw_payload' => $result->raw,
                'occurred_at' => now(),
            ]);
        });
    }
}
