<?php

namespace Tests\Feature\Shipping;

use App\Domain\Shipping\ShipmentBookingService;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Jobs\BookShipmentJob;
use App\Models\Courier;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Shipment;
use Database\Seeders\DemoCatalogSeeder;
use Database\Seeders\ShippingCourierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ShippingBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cod_checkout_creates_shipment_with_courier(): void
    {
        $this->seed(ShippingCourierSeeder::class);
        $this->seed(DemoCatalogSeeder::class);

        $variant = ProductVariant::query()->where('sku', 'URB-BLK-001')->firstOrFail();

        $this->post(route('store.cart.add'), [
            'product_variant_id' => $variant->id,
            'size_label' => 'UK 8',
            'quantity' => 1,
        ])->assertRedirect(route('store.cart'));

        $this->post(route('store.checkout.store'), [
            'full_name' => 'Test Customer',
            'phone' => '+923001234567',
            'line1' => 'Street 12',
            'city' => 'Karachi',
            'guest_email' => 'buyer@example.com',
            'payment_gateway' => 'cod',
        ])->assertRedirect(route('store.checkout.thankyou'));

        $order = Order::query()->firstOrFail();
        $shipment = Shipment::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($shipment);
        $this->assertEquals(ShipmentStatus::Pending, $shipment->status);
        $this->assertNotNull($shipment->courier_id);
    }

    public function test_booking_service_marks_generic_shipment_booked(): void
    {
        $this->seed(ShippingCourierSeeder::class);

        $generic = Courier::query()->where('code', 'generic')->firstOrFail();

        $order = Order::query()->create([
            'order_number' => 'TR-BK-01',
            'guest_email' => 'x@y.com',
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
            'payment_gateway' => 'cod',
            'subtotal' => '100',
            'discount_total' => '0',
            'shipping_total' => '200',
            'cod_fee' => '0',
            'grand_total' => '300',
            'shipping_address_snapshot' => ['full_name' => 'A', 'phone' => '1', 'line1' => 'L', 'city' => 'Lahore'],
            'preferred_courier_id' => $generic->id,
            'courier_assignment' => 'auto',
        ]);

        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'courier_id' => $generic->id,
            'status' => ShipmentStatus::Pending,
            'receiver_snapshot' => ['city' => 'Lahore'],
        ]);

        app(ShipmentBookingService::class)->book($shipment->fresh());

        $this->assertEquals(ShipmentStatus::Booked, $shipment->fresh()->status);
        $this->assertNotEmpty($shipment->fresh()->tracking_number);
    }

    public function test_book_shipment_job_dispatches_sync(): void
    {
        config(['shipping.sandbox' => true]);
        $this->seed(ShippingCourierSeeder::class);

        $courier = Courier::query()->where('adapter', 'generic')->firstOrFail();
        $order = Order::query()->create([
            'order_number' => 'TR-JOB-01',
            'guest_email' => 'x@y.com',
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
            'payment_gateway' => 'cod',
            'subtotal' => '100',
            'discount_total' => '0',
            'shipping_total' => '200',
            'cod_fee' => '0',
            'grand_total' => '300',
            'shipping_address_snapshot' => ['full_name' => 'A', 'phone' => '1', 'line1' => 'L', 'city' => 'Islamabad'],
            'preferred_courier_id' => $courier->id,
            'courier_assignment' => 'auto',
        ]);
        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'status' => ShipmentStatus::Pending,
            'receiver_snapshot' => ['city' => 'Islamabad'],
        ]);

        Bus::dispatchSync(new BookShipmentJob($shipment->id));

        $this->assertEquals(ShipmentStatus::Booked, $shipment->fresh()->status);
    }

    public function test_book_shipment_job_marks_failed_without_exception_when_courier_account_missing(): void
    {
        config(['shipping.sandbox' => false]);
        $this->seed(ShippingCourierSeeder::class);

        $postex = Courier::query()->where('code', 'postex')->firstOrFail();

        $order = Order::query()->create([
            'order_number' => 'TR-JOB-NO-ACC',
            'guest_email' => 'x@y.com',
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
            'payment_gateway' => 'cod',
            'subtotal' => '100',
            'discount_total' => '0',
            'shipping_total' => '200',
            'cod_fee' => '0',
            'grand_total' => '300',
            'shipping_address_snapshot' => ['full_name' => 'A', 'phone' => '1', 'line1' => 'L', 'city' => 'Islamabad'],
            'preferred_courier_id' => $postex->id,
            'courier_assignment' => 'auto',
        ]);

        $shipment = Shipment::query()->create([
            'order_id' => $order->id,
            'courier_id' => $postex->id,
            'courier_account_id' => null,
            'status' => ShipmentStatus::Pending,
            'receiver_snapshot' => ['city' => 'Islamabad'],
        ]);

        Bus::dispatchSync(new BookShipmentJob($shipment->id));

        $fresh = $shipment->fresh();
        $this->assertEquals(ShipmentStatus::Failed, $fresh->status);
        $this->assertStringContainsString('No active API account', (string) ($fresh->meta['booking_error'] ?? ''));
    }

    public function test_shipping_webhook_endpoint_returns_ok(): void
    {
        config(['shipping.webhook.global_secret' => 'sec']);

        $this->post(route('webhooks.shipping', ['courier' => 'postex']), [
            'tracking_number' => 'ANY',
        ])->assertStatus(401);

        $this->withHeader('X-Shipping-Secret', 'sec')->post(route('webhooks.shipping', ['courier' => 'postex']), [
            'tracking_number' => 'ANY',
        ])->assertOk();
    }
}
