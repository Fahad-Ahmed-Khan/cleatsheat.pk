<?php

namespace App\Domain\Shipping\Trax;

use App\Domain\Shipping\Couriers\TraxCourierAdapter;
use App\Models\Shipment;

final class TraxShipmentInspector
{
    /**
     * True when this shipment was "booked" via {@see TraxCourierAdapter} sandbox mode
     * (no call to Sonic), so label / cancel / receiving-sheet calls will not work.
     */
    public static function isAppSandboxBooking(Shipment $shipment): bool
    {
        $raw = $shipment->last_booking_response;
        if (is_array($raw) && (($raw['sandbox'] ?? null) === true)) {
            return true;
        }

        $t = (string) ($shipment->tracking_number ?? '');

        return (bool) preg_match('/^TRX-[A-F0-9]{6}$/', $t);
    }
}

