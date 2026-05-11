<?php

namespace App\Domain\Shipping\Contracts;

use App\Domain\Shipping\DTOs\BookingResult;
use App\Domain\Shipping\DTOs\TrackingResult;
use App\Models\Courier;
use App\Models\CourierAccount;
use App\Models\Shipment;

interface CourierAdapterInterface
{
    public function code(): string;

    public function book(Shipment $shipment, Courier $courier, ?CourierAccount $account): BookingResult;

    public function track(Shipment $shipment, Courier $courier, ?CourierAccount $account): TrackingResult;

    /**
     * Whether this adapter can return label / invoice URLs after booking.
     */
    public function supportsLabels(): bool;

    /**
     * Whether outbound webhooks are supported for this carrier (configure route separately).
     */
    public function supportsWebhooks(): bool;
}
