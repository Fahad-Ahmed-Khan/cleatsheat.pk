<?php

namespace Tests\Feature\Admin\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Courier;
use App\Models\CourierAccount;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use Database\Seeders\ShippingCourierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminTraxOpsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(Courier $courier): Order
    {
        return Order::query()->create([
            'order_number' => 'TR-TRX-ADM-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
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
    }

    public function test_admin_can_download_trax_air_waybill_pdf(): void
    {
        config(['shipping.endpoints.trax.testing' => 'https://app.sonic.pk']);

        $this->seed(ShippingCourierSeeder::class);
        $admin = User::factory()->admin()->create();

        $trax = Courier::query()->where('code', 'trax')->firstOrFail();
        $account = CourierAccount::query()->where('courier_id', $trax->id)->firstOrFail();
        $account->credentials = ['api_token' => 'tok-123', 'api_environment' => 'testing'];
        $account->save();

        $order = $this->makeOrder($trax);
        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'courier_id' => $trax->id,
            'courier_account_id' => $account->id,
            'status' => ShipmentStatus::Booked,
            'tracking_number' => '101101000405',
            'receiver_snapshot' => $order->shipping_address_snapshot,
        ]);

        Http::fake(fn ($req) => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']));

        $resp = $this->actingAs($admin)->get(route('admin.orders.shipment.trax.air-waybill', [$order->id, $shipment->id]));

        $resp->assertOk();
        $resp->assertHeader('content-type', 'application/pdf');

        Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'tok-123'));
    }

    public function test_admin_can_generate_trax_receiving_sheet_pdf(): void
    {
        config(['shipping.endpoints.trax.testing' => 'https://app.sonic.pk']);

        $this->seed(ShippingCourierSeeder::class);
        $admin = User::factory()->admin()->create();

        $trax = Courier::query()->where('code', 'trax')->firstOrFail();
        $account = CourierAccount::query()->where('courier_id', $trax->id)->firstOrFail();
        $account->credentials = ['api_token' => 'tok-123', 'api_environment' => 'testing'];
        $account->save();

        $order = $this->makeOrder($trax);
        Shipment::query()->create([
            'order_id' => $order->id,
            'courier_id' => $trax->id,
            'courier_account_id' => $account->id,
            'status' => ShipmentStatus::Booked,
            'tracking_number' => '101101000405',
            'receiver_snapshot' => $order->shipping_address_snapshot,
        ]);

        Http::fake([
            'https://app.sonic.pk/api/receiving_sheet/create' => Http::response([
                'status' => 0,
                'message' => 'Receiving Sheet has been Created',
                'receiving_sheet_id' => 6158,
            ], 200),
            'https://app.sonic.pk/api/receiving_sheet/view*' => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']),
        ]);

        $resp = $this->actingAs($admin)->get(route('admin.orders.trax.receiving-sheet', $order->id));

        $resp->assertOk();
        $resp->assertHeader('content-type', 'application/pdf');

        Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'tok-123'));
    }

    public function test_admin_can_cancel_trax_shipment(): void
    {
        config(['shipping.endpoints.trax.testing' => 'https://app.sonic.pk']);

        $this->seed(ShippingCourierSeeder::class);
        $admin = User::factory()->admin()->create();

        $trax = Courier::query()->where('code', 'trax')->firstOrFail();
        $account = CourierAccount::query()->where('courier_id', $trax->id)->firstOrFail();
        $account->credentials = ['api_token' => 'tok-123', 'api_environment' => 'testing'];
        $account->save();

        $order = $this->makeOrder($trax);
        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'courier_id' => $trax->id,
            'courier_account_id' => $account->id,
            'status' => ShipmentStatus::Booked,
            'tracking_number' => '101101000405',
            'receiver_snapshot' => $order->shipping_address_snapshot,
        ]);

        Http::fake([
            'https://app.sonic.pk/api/shipment/cancel' => Http::response([
                'status' => 0,
                'message' => 'Shipment is Cancelled',
            ], 200),
        ]);

        $resp = $this->actingAs($admin)->post(route('admin.orders.shipment.trax.cancel', [$order->id, $shipment->id]));

        $resp->assertRedirect(route('admin.orders.show', $order));
        $this->assertSame(ShipmentStatus::Canceled, $shipment->fresh()->status);
        $this->assertTrue(ShipmentEvent::query()
            ->where('shipment_id', $shipment->id)
            ->where('status', ShipmentStatus::Canceled->value)
            ->exists());
    }
}

