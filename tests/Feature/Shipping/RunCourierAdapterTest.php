<?php

namespace Tests\Feature\Shipping;

use App\Domain\Shipping\ShipmentBookingService;
use App\Domain\Shipping\ShippingTrackingSyncService;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Courier;
use App\Models\CourierAccount;
use App\Models\Order;
use App\Models\Shipment;
use Database\Seeders\ShippingCourierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunCourierAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_and_tracking_hit_run_courier_endpoints(): void
    {
        config(['shipping.sandbox' => false]);

        $this->seed(ShippingCourierSeeder::class);

        $courier = Courier::query()->where('adapter', 'runcourier')->firstOrFail();
        $account = CourierAccount::query()->where('courier_id', $courier->id)->firstOrFail();
        $account->credentials = [
            'api_token' => 'test-auth-key',
            'client_code' => '1001',
            'profile_id' => '2002',
            'api_vendor' => 'auto',
        ];
        $account->save();

        Http::fake([
            'https://portal.runcourier.com/API/CreateOrder.php' => Http::response([
                'tracking_no' => 110010208,
                'thirdparty_tracking_no' => null,
                'thirdparty_name' => '0',
                'id' => 68765,
                'invoice_link' => 'https://portal.runcourier.com/invoicehtml.php?order_id=68765&booking=1',
                'message' => 'Order 110010208 created successfully',
            ], 200),
            'https://portal.runcourier.com/API/CurrentStatus.php' => Http::response([
                [
                    'tracking_no' => '110010208',
                    'status' => 'Out for Delivery',
                    'created' => '2025-02-27 10:00:00',
                ],
            ], 200),
        ]);

        $order = Order::query()->create([
            'order_number' => 'TR-RC-01',
            'guest_email' => 'buyer@example.com',
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
            'payment_gateway' => 'cod',
            'subtotal' => '100',
            'discount_total' => '0',
            'shipping_total' => '200',
            'cod_fee' => '0',
            'grand_total' => '300',
            'shipping_address_snapshot' => [
                'full_name' => 'Test User',
                'phone' => '+923001234567',
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
            'cod_amount' => '300',
            'weight_kg' => 1,
            'sender_snapshot' => ['city' => 'Lahore'],
            'receiver_snapshot' => [
                'full_name' => 'Test User',
                'phone' => '+923001234567',
                'line1' => 'Street 1',
                'city' => 'Karachi',
            ],
        ]);

        app(ShipmentBookingService::class)->book($shipment->fresh(['order', 'courier', 'courierAccount']));

        $fresh = $shipment->fresh();
        $this->assertEquals(ShipmentStatus::Booked, $fresh->status);
        $this->assertSame('110010208', $fresh->tracking_number);
        $this->assertSame('68765', $fresh->booking_reference);
        $this->assertStringContainsString('invoicehtml.php', (string) $fresh->invoice_url);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'CreateOrder.php')) {
                return false;
            }
            $data = $request->data();
            $this->assertSame('test-auth-key', $data['auth_key']);
            $this->assertSame('1001', $data['client_code']);
            $this->assertSame('2002', $data['profile_id']);
            $this->assertSame('Lahore', $data['origin']);
            $this->assertSame('Karachi', $data['destination']);

            return true;
        });

        app(ShippingTrackingSyncService::class)->syncShipment($shipment->fresh(['order', 'courier', 'courierAccount']));

        $this->assertEquals(ShipmentStatus::InTransit, $shipment->fresh()->status);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'CurrentStatus.php')) {
                return false;
            }
            $data = $request->data();

            return ($data['tracking_no'] ?? null) === '110010208'
                && ($data['auth_key'] ?? null) === 'test-auth-key';
        });
    }
}
