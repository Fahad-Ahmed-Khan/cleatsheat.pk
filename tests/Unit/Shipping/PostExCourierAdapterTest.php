<?php

namespace Tests\Unit\Shipping;

use App\Domain\Shipping\Couriers\PostExCourierAdapter;
use App\Domain\Shipping\CourierApiLogger;
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

class PostExCourierAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_postex_booking_uses_token_header_and_v3_endpoint(): void
    {
        config(['shipping.sandbox' => false]);
        config(['shipping.endpoints.postex' => 'https://api.postex.pk']);

        $settings = ShippingSetting::current();
        $settings->postex_pickup_address_code = 'PICK-001';
        $settings->postex_store_address_code = 'STORE-001';
        $settings->save();

        $courier = Courier::query()->create([
            'code' => 'postex',
            'name' => 'PostEx',
            'adapter' => 'postex',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $account = CourierAccount::query()->create([
            'courier_id' => $courier->id,
            'name' => 'Main',
            'credentials' => ['api_token' => 'tok-123'],
            'service_code' => null,
            'cod_allowed' => true,
            'city_restrictions' => null,
            'is_active' => true,
            'is_default' => true,
        ]);

        $order = Order::query()->create([
            'order_number' => 'TR-PX-01',
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
            'https://api.postex.pk/services/integration/api/order/v3/create-order' => Http::response([
                'statusCode' => '200',
                'statusMessage' => 'ORDER HAS BEEN CREATED',
                'dist' => [
                    'trackingNumber' => 'CX-123',
                    'orderStatus' => 'UnBooked',
                    'orderDate' => '2026-05-08 12:00:00',
                ],
            ], 200),
        ]);

        $logger = $this->createMock(CourierApiLogger::class);
        $adapter = new PostExCourierAdapter($logger);

        $result = $adapter->book($shipment, $courier, $account);

        $this->assertTrue($result->success);
        $this->assertSame('CX-123', $result->trackingNumber);

        Http::assertSent(function ($req) {
            return $req->url() === 'https://api.postex.pk/services/integration/api/order/v3/create-order'
                && $req->method() === 'POST'
                && $req->hasHeader('token', 'tok-123')
                && ($req['orderRefNumber'] ?? null) === 'TR-PX-01'
                && ($req['pickupAddressCode'] ?? null) === 'PICK-001'
                && ($req['storeAddressCode'] ?? null) === 'STORE-001';
        });
    }

    public function test_postex_tracking_maps_delivered_code(): void
    {
        config(['shipping.sandbox' => false]);
        config(['shipping.endpoints.postex' => 'https://api.postex.pk']);

        $courier = Courier::query()->create([
            'code' => 'postex',
            'name' => 'PostEx',
            'adapter' => 'postex',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $account = CourierAccount::query()->create([
            'courier_id' => $courier->id,
            'name' => 'Main',
            'credentials' => ['api_token' => 'tok-123'],
            'service_code' => null,
            'cod_allowed' => true,
            'city_restrictions' => null,
            'is_active' => true,
            'is_default' => true,
        ]);

        $order = Order::query()->create([
            'order_number' => 'TR-PX-02',
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
            'status' => ShipmentStatus::Booked,
            'tracking_number' => 'CX-999',
            'receiver_snapshot' => $order->shipping_address_snapshot,
        ]);

        Http::fake([
            'https://api.postex.pk/services/integration/api/order/v1/track-order/CX-999' => Http::response([
                'statusCode' => '200',
                'statusMessage' => 'SUCCESSFULLY OPERATED',
                'dist' => [
                    'transactionStatus' => 'Delivered',
                    'transactionStatusHistory' => [
                        ['transactionStatusMessageCode' => '0001', 'transactionStatusMessage' => 'At Merchant Warehouse'],
                        ['transactionStatusMessageCode' => '0005', 'transactionStatusMessage' => 'Delivered'],
                    ],
                ],
            ], 200),
        ]);

        $logger = $this->createMock(CourierApiLogger::class);
        $adapter = new PostExCourierAdapter($logger);

        $result = $adapter->track($shipment, $courier, $account);

        $this->assertSame(ShipmentStatus::Delivered, $result->status);

        Http::assertSent(fn ($req) => $req->method() === 'GET' && $req->hasHeader('token', 'tok-123'));
    }
}

