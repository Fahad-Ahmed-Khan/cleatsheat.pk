<?php

namespace Tests\Feature\Shipping;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Jobs\SyncShipmentTrackingJob;
use App\Models\Courier;
use App\Models\CourierAccount;
use App\Models\Order;
use App\Models\Shipment;
use Database\Seeders\ShippingCourierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class TraxWebhookStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_trax_webhook_updates_shipment_status_and_queues_sync(): void
    {
        Bus::fake();
        config(['shipping.webhook.global_secret' => 'sec']);

        $this->seed(ShippingCourierSeeder::class);
        $trax = Courier::query()->where('code', 'trax')->firstOrFail();
        $account = CourierAccount::query()->where('courier_id', $trax->id)->firstOrFail();

        $order = Order::query()->create([
            'order_number' => 'TR-TRX-WH-01',
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
            'preferred_courier_id' => $trax->id,
            'courier_assignment' => 'auto',
        ]);

        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'courier_id' => $trax->id,
            'courier_account_id' => $account->id,
            'status' => ShipmentStatus::InTransit,
            'tracking_number' => '101101000405',
        ]);

        $this->withHeader('X-Shipping-Secret', 'sec')->post(route('webhooks.shipping', ['courier' => 'trax']), [
            'tracking_number' => '101101000405',
            'status' => 'Shipment - Delivered',
            'date_time' => '2026-06-16 18:00:00',
        ])->assertOk();

        $this->assertSame(ShipmentStatus::Delivered, $shipment->fresh()->status);
        Bus::assertDispatched(SyncShipmentTrackingJob::class);
    }
}

