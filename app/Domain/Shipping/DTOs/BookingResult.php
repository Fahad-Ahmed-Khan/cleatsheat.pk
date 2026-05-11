<?php

namespace App\Domain\Shipping\DTOs;

final class BookingResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $trackingNumber = null,
        public readonly ?string $bookingReference = null,
        public readonly ?string $labelUrl = null,
        public readonly ?string $invoiceUrl = null,
        public readonly ?string $shippingCharges = null,
        public readonly array $raw = [],
        public readonly ?string $errorMessage = null,
    ) {}
}
