<?php

namespace App\Domain\Shipping\PostEx;

use App\Domain\Shipping\Couriers\PostExCourierAdapter;
use App\Models\Shipment;

final class PostExShipmentInspector
{
    /**
     * True when this shipment was "booked" via {@see PostExCourierAdapter} sandbox mode
     * (no call to PostEx), so invoice / load-sheet / cancel against PostEx will not work.
     */
    public static function isAppSandboxBooking(Shipment $shipment): bool
    {
        $raw = $shipment->last_booking_response;
        if (is_array($raw) && (($raw['sandbox'] ?? null) === true)) {
            return true;
        }

        $t = (string) ($shipment->tracking_number ?? '');

        return (bool) preg_match('/^PEX-[A-F0-9]{6}$/', $t);
    }
}
