<?php

namespace Tests\Unit\Domain\Shipping\PostEx;

use App\Domain\Shipping\PostEx\PostExShipmentInspector;
use App\Models\Shipment;
use PHPUnit\Framework\TestCase;

class PostExShipmentInspectorTest extends TestCase
{
    public function test_detects_sandbox_flag_in_last_booking_response(): void
    {
        $s = new Shipment;
        $s->tracking_number = 'CX-999';
        $s->last_booking_response = ['sandbox' => true];

        $this->assertTrue(PostExShipmentInspector::isAppSandboxBooking($s));
    }

    public function test_detects_pex_sandbox_tracking_pattern(): void
    {
        $s = new Shipment;
        $s->tracking_number = 'PEX-A1B2C3';
        $s->last_booking_response = null;

        $this->assertTrue(PostExShipmentInspector::isAppSandboxBooking($s));
    }

    public function test_live_style_tracking_not_sandbox(): void
    {
        $s = new Shipment;
        $s->tracking_number = 'CX-12345';
        $s->last_booking_response = ['statusCode' => '200'];

        $this->assertFalse(PostExShipmentInspector::isAppSandboxBooking($s));
    }
}
