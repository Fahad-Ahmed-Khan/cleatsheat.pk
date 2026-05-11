<?php

namespace Tests\Feature\Admin\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Jobs\BookShipmentJob;
use App\Jobs\SyncShipmentTrackingJob;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\ShippingCourierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AdminOrderBookingTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(?int $preferredCourierId = null): Order
    {
        return Order::query()->create([
            'order_number' => 'TR-ADM-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
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
                'phone' => '+923001112233',
                'line1' => 'Street 1',
                'city' => 'Karachi',
            ],
            'preferred_courier_id' => $preferredCourierId,
            'courier_assignment' => 'auto',
        ]);
    }

    public function test_admin_show_page_loads_with_couriers_and_default(): void
    {
        $this->seed(ShippingCourierSeeder::class);
        $admin = User::factory()->admin()->create();

        $runCourier = Courier::query()->where('code', 'runcourier')->firstOrFail();
        $order = $this->makeOrder($runCourier->id);

        $resp = $this->actingAs($admin)->get(route('admin.orders.show', $order));

        $resp->assertOk();
        $resp->assertInertia(fn ($page) => $page
            ->component('Admin/Orders/Show')
            ->where('defaultBookingCourierId', $runCourier->id)
            ->has('couriers', fn ($couriers) => $couriers
                ->where('0.code', 'leopards')
                ->etc()
            )
        );
    }

    public function test_admin_can_book_order_with_chosen_courier(): void
    {
        Bus::fake();
        $this->seed(ShippingCourierSeeder::class);

        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder();

        $runCourier = Courier::query()->where('code', 'runcourier')->firstOrFail();

        $resp = $this->actingAs($admin)->post(
            route('admin.orders.shipment.book', $order),
            ['courier_id' => $runCourier->id],
        );

        $resp->assertRedirect(route('admin.orders.show', $order));
        $resp->assertSessionHas('status');

        $shipment = Shipment::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame($runCourier->id, $shipment->courier_id);
        $this->assertSame(ShipmentStatus::Pending, $shipment->status);

        Bus::assertDispatched(BookShipmentJob::class, fn ($job) => $job->shipmentId === $shipment->id);
    }

    public function test_book_falls_back_to_resolved_default_courier_when_none_provided(): void
    {
        Bus::fake();
        $this->seed(ShippingCourierSeeder::class);

        $admin = User::factory()->admin()->create();
        $tcs = Courier::query()->where('code', 'tcs')->firstOrFail();
        $order = $this->makeOrder($tcs->id);

        $resp = $this->actingAs($admin)->post(route('admin.orders.shipment.book', $order));

        $resp->assertRedirect(route('admin.orders.show', $order));

        $shipment = Shipment::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame($tcs->id, $shipment->courier_id);
        Bus::assertDispatched(BookShipmentJob::class);
    }

    public function test_book_swaps_courier_on_an_existing_pending_shipment_instead_of_creating_a_new_one(): void
    {
        Bus::fake();
        $this->seed(ShippingCourierSeeder::class);

        $admin = User::factory()->admin()->create();
        $leopards = Courier::query()->where('code', 'leopards')->firstOrFail();
        $runCourier = Courier::query()->where('code', 'runcourier')->firstOrFail();

        $order = $this->makeOrder($leopards->id);

        $existing = Shipment::query()->create([
            'order_id' => $order->id,
            'courier_id' => $leopards->id,
            'status' => ShipmentStatus::Pending,
            'receiver_snapshot' => ['city' => 'Karachi'],
        ]);

        $this->actingAs($admin)->post(
            route('admin.orders.shipment.book', $order),
            ['courier_id' => $runCourier->id],
        )->assertRedirect();

        $this->assertSame(1, Shipment::query()->where('order_id', $order->id)->count());
        $this->assertSame($runCourier->id, $existing->fresh()->courier_id);
        Bus::assertDispatched(BookShipmentJob::class, fn ($job) => $job->shipmentId === $existing->id);
    }

    public function test_book_rejects_invalid_courier_id(): void
    {
        Bus::fake();
        $this->seed(ShippingCourierSeeder::class);

        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder();

        $resp = $this->actingAs($admin)->post(
            route('admin.orders.shipment.book', $order),
            ['courier_id' => 999_999],
        );

        $resp->assertSessionHasErrors('courier_id');
        Bus::assertNotDispatched(BookShipmentJob::class);
    }

    public function test_sync_tracking_dispatches_for_shipments_with_a_tracking_number(): void
    {
        Bus::fake();
        $this->seed(ShippingCourierSeeder::class);

        $admin = User::factory()->admin()->create();
        $courier = Courier::query()->where('code', 'leopards')->firstOrFail();
        $order = $this->makeOrder($courier->id);

        $with = Shipment::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'status' => ShipmentStatus::Booked,
            'tracking_number' => 'TRK-001',
            'receiver_snapshot' => ['city' => 'Lahore'],
        ]);

        Shipment::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'status' => ShipmentStatus::Pending,
            'tracking_number' => null,
            'receiver_snapshot' => ['city' => 'Lahore'],
        ]);

        $this->actingAs($admin)->post(route('admin.orders.shipment.sync-tracking', $order))
            ->assertRedirect();

        Bus::assertDispatchedTimes(SyncShipmentTrackingJob::class, 1);
        Bus::assertDispatched(SyncShipmentTrackingJob::class, fn ($job) => $job->shipmentId === $with->id);
    }
}
