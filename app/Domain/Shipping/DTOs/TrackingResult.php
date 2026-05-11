<?php

namespace App\Domain\Shipping\DTOs;

use App\Enums\ShipmentStatus;

final class TrackingResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ShipmentStatus $status,
        public readonly array $raw = [],
        public readonly ?string $publicMessage = null,
    ) {}
}
