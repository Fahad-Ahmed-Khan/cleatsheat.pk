<?php

namespace App\Domain\Shipping\Couriers;

use App\Domain\Shipping\AbstractCourierAdapter;
use App\Domain\Shipping\DTOs\BookingResult;
use App\Domain\Shipping\DTOs\TrackingResult;
use App\Enums\ShipmentStatus;
use App\Models\Courier;
use App\Models\CourierAccount;
use App\Models\Shipment;

/**
 * Offline / manual fulfilment — generates local references without remote APIs.
 */
class GenericCourierAdapter extends AbstractCourierAdapter
{
    public function code(): string
    {
        return 'generic';
    }

    public function supportsLabels(): bool
    {
        return false;
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }

    public function book(Shipment $shipment, Courier $courier, ?CourierAccount $account): BookingResult
    {
        $order = $shipment->order;
        $tn = 'MN-'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT);

        return new BookingResult(
            success: true,
            trackingNumber: $tn,
            bookingReference: $order->order_number,
            raw: ['note' => 'Manual routing — assign tracking in courier portal if needed'],
        );
    }

    public function track(Shipment $shipment, Courier $courier, ?CourierAccount $account): TrackingResult
    {
        return new TrackingResult(
            status: $shipment->status,
            raw: ['note' => 'No live tracking for generic adapter'],
        );
    }
}
