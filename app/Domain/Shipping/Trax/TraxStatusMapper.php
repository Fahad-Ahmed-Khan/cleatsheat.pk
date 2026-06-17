<?php

namespace App\Domain\Shipping\Trax;

use App\Enums\ShipmentStatus;

final class TraxStatusMapper
{
    public static function fromText(?string $remote): ShipmentStatus
    {
        $s = strtolower(trim((string) $remote));

        return match (true) {
            $s === '' => ShipmentStatus::Booked,
            str_contains($s, 'deliver') => ShipmentStatus::Delivered,
            str_contains($s, 'cancel') => ShipmentStatus::Canceled,
            str_contains($s, 'return') || str_contains($s, 'rto') => ShipmentStatus::Failed,
            str_contains($s, 'fail') || str_contains($s, 'unsuccess') => ShipmentStatus::Failed,
            str_contains($s, 'out for delivery') => ShipmentStatus::InTransit,
            str_contains($s, 'arrived') || str_contains($s, 'in transit') || str_contains($s, 'transit') => ShipmentStatus::InTransit,
            str_contains($s, 'book') => ShipmentStatus::Booked,
            default => ShipmentStatus::Booked,
        };
    }
}

