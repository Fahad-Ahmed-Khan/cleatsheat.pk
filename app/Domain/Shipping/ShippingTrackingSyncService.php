<?php

namespace App\Domain\Shipping;

use App\Domain\Notifications\WhatsApp\ShipmentStatusCustomerAlertService;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use Illuminate\Support\Facades\DB;

class ShippingTrackingSyncService
{
    public function __construct(
        private readonly CourierRegistry $registry,
        private readonly ShipmentStatusCustomerAlertService $customerAlerts,
    ) {}

    public function syncShipment(Shipment $shipment, bool $force = false): void
    {
        $shipment->load(['order', 'courier', 'courierAccount']);

        $courier = $shipment->courier;
        if ($courier === null || $shipment->tracking_number === null) {
            return;
        }

        // Skip terminal states for automatic/scheduled syncs to avoid courier flap. An
        // operator-initiated sync passes $force=true so we can reconcile late updates
        // (e.g. a Failed booking that's now been cancelled on the courier portal).
        if (! $force && in_array($shipment->status, [ShipmentStatus::Delivered, ShipmentStatus::Canceled, ShipmentStatus::Failed], true)) {
            return;
        }

        $adapter = $this->registry->forCourier($courier);
        $result = $adapter->track($shipment, $courier, $shipment->courierAccount);

        $previous = $shipment->status;

        DB::transaction(function () use ($shipment, $result): void {
            $shipment->last_tracking_response = $result->raw;
            $prev = $shipment->status;

            if ($result->status !== $prev) {
                $shipment->status = $result->status;
                if ($result->status === ShipmentStatus::Delivered) {
                    $shipment->delivered_at = now();
                }
                if ($result->status === ShipmentStatus::Failed) {
                    $shipment->failed_at = now();
                }
            }
            $shipment->save();

            if ($result->status !== $prev) {
                ShipmentEvent::query()->create([
                    'shipment_id' => $shipment->id,
                    'status' => $result->status->value,
                    'description' => $result->publicMessage ?? 'Status updated',
                    'raw_payload' => $result->raw,
                    'occurred_at' => now(),
                ]);
            }
        });

        if ($result->status !== $previous) {
            $shipment->refresh();
            $this->customerAlerts->notifyStatusTransition($shipment, $previous, $result->status);
        }
    }
}
