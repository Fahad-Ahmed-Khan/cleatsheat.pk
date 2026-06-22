<?php

namespace Tests\Unit\Shipping;

use App\Domain\Shipping\CourierApiLogger;
use App\Domain\Shipping\Couriers\TraxCourierAdapter;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Courier;
use App\Models\CourierAccount;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TraxCourierAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_trax_booking_posts_to_sonic_with_authorization_header(): void
    {
        config(['shipping.sandbox' => false]);
        config(['shipping.endpoints.trax.testing' => 'https://app.sonic.pk']);

        $settings = ShippingSetting::current();
        $settings->trax_pickup_address_id = 3015;
        $settings->trax_shipping_mode_id = 1;
        $settings->trax_charges_mode_id = 4;
        $settings->trax_item_product_type_id = 24;
        $settings->trax_delivery_type_id = 1;
        $settings->save();

        $courier = Courier::query()->where('code', 'trax')->firstOrFail();
        $account = CourierAccount::query()->where('courier_id', $courier->id)->firstOrFail();
        $account->credentials = ['api_token' => 'tok-123', 'api_environment' => 'testing'];
        $account->save();

        $order = Order::query()->create([
            'order_number' => 'TR-TRX-01',
            'guest_email' => 'buyer@example.com',
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
            'payment_gateway' => 'cod',
            'subtotal' => '1000',
            'discount_total' => '0',
            'shipping_total' => '200',
            'cod_fee' => '0',
            'grand_total' => '1200',
            'shipping_address_snapshot' => [
                'full_name' => 'Buyer',
                'phone' => '03001234567',
                'line1' => 'Street 1',
                'city' => 'Karachi',
            ],
            'preferred_courier_id' => $courier->id,
            'courier_assignment' => 'auto',
        ]);

        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'courier_account_id' => $account->id,
            'status' => ShipmentStatus::Pending,
            'cod_amount' => '1200',
            'weight_kg' => '1.000',
            'receiver_snapshot' => $order->shipping_address_snapshot,
        ]);
        $shipment->load(['order', 'order.items']);

        Http::fake([
            'https://app.sonic.pk/api/cities' => Http::response([
                'status' => 0,
                'cities' => [
                    ['id' => 202, 'name' => 'Karachi', 'pickup' => true],
                ],
            ], 200),
            'https://app.sonic.pk/api/shipment/book' => Http::response([
                'status' => 0,
                'message' => 'Shipment has been Booked!',
                'tracking number' => '101101000405',
            ], 200),
        ]);

        $logger = $this->createMock(CourierApiLogger::class);
        $adapter = new TraxCourierAdapter($logger);

        $result = $adapter->book($shipment, $courier, $account);

        $this->assertTrue($result->success);
        $this->assertSame('101101000405', $result->trackingNumber);

        Http::assertSent(function ($req) {
            return $req->url() === 'https://app.sonic.pk/api/shipment/book'
                && $req->method() === 'POST'
                && $req->hasHeader('Authorization', 'tok-123')
                && ($req['pickup_address_id'] ?? null) === 3015
                && ($req['consignee_city_id'] ?? null) === 202
                && ($req['shipping_mode_id'] ?? null) === 1
                && ($req['charges_mode_id'] ?? null) === 4;
        });
    }

    public function test_trax_tracking_maps_delivered_status_text(): void
    {
        config(['shipping.sandbox' => false]);
        config(['shipping.endpoints.trax.testing' => 'https://app.sonic.pk']);

        $courier = Courier::query()->where('code', 'trax')->firstOrFail();
        $account = CourierAccount::query()->where('courier_id', $courier->id)->firstOrFail();
        $account->credentials = ['api_token' => 'tok-123', 'api_environment' => 'testing'];
        $account->save();

        $order = Order::query()->create([
            'order_number' => 'TR-TRX-02',
            'guest_email' => 'buyer@example.com',
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
            'payment_gateway' => 'cod',
            'subtotal' => '1000',
            'discount_total' => '0',
            'shipping_total' => '200',
            'cod_fee' => '0',
            'grand_total' => '1200',
            'shipping_address_snapshot' => [
                'full_name' => 'Buyer',
                'phone' => '03001234567',
                'line1' => 'Street 1',
                'city' => 'Karachi',
            ],
            'preferred_courier_id' => $courier->id,
            'courier_assignment' => 'auto',
        ]);

        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'courier_account_id' => $account->id,
            'status' => ShipmentStatus::InTransit,
            'tracking_number' => '101101000405',
            'receiver_snapshot' => $order->shipping_address_snapshot,
        ]);

        Http::fake([
            'https://app.sonic.pk/api/shipment/track*' => Http::response([
                'status' => 0,
                'message' => 'Tracking',
                'details' => [
                    'tracking_history' => [
                        ['status' => 'Shipment - Delivered'],
                    ],
                ],
            ], 200),
        ]);

        $logger = $this->createMock(CourierApiLogger::class);
        $adapter = new TraxCourierAdapter($logger);

        $result = $adapter->track($shipment, $courier, $account);
        $this->assertSame(ShipmentStatus::Delivered, $result->status);
    }
}

