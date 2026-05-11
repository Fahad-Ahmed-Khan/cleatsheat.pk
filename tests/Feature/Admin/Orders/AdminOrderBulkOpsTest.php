<?php

namespace Tests\Feature\Admin\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Jobs\BookShipmentJob;
use App\Jobs\SyncShipmentTrackingJob;
use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderAuditEvent;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\ShippingCourierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AdminOrderBulkOpsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(array $ship = null, ?int $preferredCourierId = null): Order
    {
        return Order::query()->create([
            'order_number' => 'TR-BULK-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'guest_email' => 'buyer@example.com',
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
            'payment_gateway' => 'cod',
            'subtotal' => '1000',
            'discount_total' => '0',
            'shipping_total' => '200',
            'cod_fee' => '0',
            'grand_total' => '1200',
            'shipping_address_snapshot' => $ship ?? [
                'full_name' => 'Buyer',
                'phone' => '03001234567',
                'line1' => 'Street 1',
                'city' => 'Karachi',
            ],
            'billing_address_snapshot' => [
                'full_name' => 'Buyer',
                'phone' => '03001234567',
                'line1' => 'Street 1',
                'city' => 'Karachi',
            ],
            'preferred_courier_id' => $preferredCourierId,
            'courier_assignment' => 'auto',
        ]);
    }

    public function test_guest_cannot_call_bulk_endpoints(): void
    {
        $order = $this->makeOrder();

        $this->post(route('admin.orders.bulk.book'), [
            'order_ids' => [$order->id],
            'mode' => 'auto',
        ])->assertRedirect();
    }

    public function test_non_admin_is_forbidden_by_admin_middleware(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder();

        $this->actingAs($user)->post(route('admin.orders.bulk.sync-tracking'), [
            'order_ids' => [$order->id],
        ])->assertStatus(403);
    }

    public function test_bulk_book_validates_payload(): void
    {
        $admin = User::factory()->admin()->create();

        $resp = $this->actingAs($admin)->post(route('admin.orders.bulk.book'), [
            'order_ids' => [],
            'mode' => 'manual',
        ]);

        $resp->assertSessionHasErrors(['order_ids', 'courier_id']);
    }

    public function test_bulk_book_dispatches_jobs_and_skips_missing_address(): void
    {
        Bus::fake();
        $this->seed(ShippingCourierSeeder::class);

        $admin = User::factory()->admin()->create();
        $courier = Courier::query()->where('code', 'leopards')->firstOrFail();

        $ok = $this->makeOrder(preferredCourierId: $courier->id);
        $bad = $this->makeOrder(['full_name' => '', 'phone' => '', 'line1' => '', 'city' => 'Karachi'], $courier->id);

        $resp = $this->actingAs($admin)->post(route('admin.orders.bulk.book'), [
            'order_ids' => [$ok->id, $bad->id],
            'mode' => 'auto',
        ]);

        $resp->assertRedirect();
        Bus::assertDispatched(BookShipmentJob::class);
    }

    public function test_bulk_sync_tracking_dispatches_for_shipments_with_tracking_number(): void
    {
        Bus::fake();
        $this->seed(ShippingCourierSeeder::class);

        $admin = User::factory()->admin()->create();
        $courier = Courier::query()->where('code', 'leopards')->firstOrFail();
        $order = $this->makeOrder(preferredCourierId: $courier->id);

        Shipment::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'status' => ShipmentStatus::Booked,
            'tracking_number' => 'TRK-001',
            'receiver_snapshot' => $order->shipping_address_snapshot,
        ]);

        $this->actingAs($admin)->post(route('admin.orders.bulk.sync-tracking'), [
            'order_ids' => [$order->id],
        ])->assertRedirect();

        Bus::assertDispatched(SyncShipmentTrackingJob::class);
    }

    public function test_bulk_status_update_creates_audit_event(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder();

        $this->actingAs($admin)->patch(route('admin.orders.bulk.update-status'), [
            'order_ids' => [$order->id],
            'status' => OrderStatus::Shipped->value,
        ])->assertRedirect();

        $this->assertTrue(OrderAuditEvent::query()
            ->where('order_id', $order->id)
            ->where('event_type', 'bulk_status_update')
            ->exists());
    }
}

