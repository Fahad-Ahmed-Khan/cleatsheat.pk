<?php

namespace Tests\Unit\Shipping;

use App\Domain\Shipping\Couriers\GenericCourierAdapter;
use App\Domain\Shipping\Couriers\LeopardsCourierAdapter;
use App\Domain\Shipping\Couriers\MpCourierAdapter;
use App\Domain\Shipping\Couriers\PostExCourierAdapter;
use App\Domain\Shipping\Couriers\TcsCourierAdapter;
use App\Domain\Shipping\CourierApiLogger;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierAdaptersTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrderAndShipment(Courier $courier): Shipment
    {
        $order = Order::query()->create([
            'order_number' => 'TR-U-'.strtoupper(bin2hex(random_bytes(4))),
            'user_id' => null,
            'guest_email' => 'g@e.com',
            'guest_token' => null,
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
            'payment_gateway' => 'cod',
            'coupon_id' => null,
            'preferred_courier_id' => null,
            'courier_assignment' => 'auto',
            'subtotal' => '100.00',
            'discount_total' => '0',
            'shipping_total' => '200.00',
            'cod_fee' => '0',
            'grand_total' => '300.00',
            'shipping_address_snapshot' => [
                'full_name' => 'A', 'phone' => '1', 'line1' => 'L', 'city' => 'Karachi',
            ],
            'billing_address_snapshot' => null,
            'customer_notes' => null,
        ]);

        return Shipment::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'courier_account_id' => null,
            'status' => ShipmentStatus::Pending,
            'receiver_snapshot' => ['full_name' => 'A', 'phone' => '1', 'city' => 'Karachi'],
        ]);
    }

    public function test_generic_adapter_produces_local_tracking(): void
    {
        $courier = Courier::query()->create([
            'code' => 'g', 'name' => 'G', 'adapter' => 'generic', 'is_active' => true, 'sort_order' => 0,
        ]);
        $shipment = $this->makeOrderAndShipment($courier);
        $shipment->load('order');

        $adapter = new GenericCourierAdapter;
        $result = $adapter->book($shipment, $courier, null);

        $this->assertTrue($result->success);
        $this->assertNotEmpty($result->trackingNumber);
    }

    public function test_leopards_sandbox_booking_success(): void
    {
        config(['shipping.sandbox' => true]);

        $logger = $this->createMock(CourierApiLogger::class);
        $adapter = new LeopardsCourierAdapter($logger);
        $courier = Courier::query()->create([
            'code' => 'l', 'name' => 'L', 'adapter' => 'leopards', 'is_active' => true, 'sort_order' => 0,
        ]);
        $shipment = $this->makeOrderAndShipment($courier);
        $shipment->load('order');

        $r = $adapter->book($shipment, $courier, null);
        $this->assertTrue($r->success);
        $this->assertStringContainsString('LEO-', (string) $r->trackingNumber);
    }

    public function test_mp_postex_tcs_sandbox_track_returns_in_transit(): void
    {
        config(['shipping.sandbox' => true]);
        $logger = $this->createMock(CourierApiLogger::class);

        $courier = Courier::query()->create([
            'code' => 'm', 'name' => 'M', 'adapter' => 'mp', 'is_active' => true, 'sort_order' => 0,
        ]);
        $shipment = $this->makeOrderAndShipment($courier);
        $shipment->load('order');

        $a = new MpCourierAdapter($logger);
        $t = $a->track($shipment, $courier, null);
        $this->assertEquals(ShipmentStatus::InTransit, $t->status);

        $courier2 = Courier::query()->create([
            'code' => 'p', 'name' => 'P', 'adapter' => 'postex', 'is_active' => true, 'sort_order' => 0,
        ]);
        $shipment2 = $this->makeOrderAndShipment($courier2);
        $shipment2->load('order');
        $p = new PostExCourierAdapter($logger);
        $this->assertEquals(ShipmentStatus::InTransit, $p->track($shipment2, $courier2, null)->status);

        $courier3 = Courier::query()->create([
            'code' => 't', 'name' => 'T', 'adapter' => 'tcs', 'is_active' => true, 'sort_order' => 0,
        ]);
        $shipment3 = $this->makeOrderAndShipment($courier3);
        $shipment3->load('order');
        $tc = new TcsCourierAdapter($logger);
        $this->assertEquals(ShipmentStatus::InTransit, $tc->track($shipment3, $courier3, null)->status);
    }
}
