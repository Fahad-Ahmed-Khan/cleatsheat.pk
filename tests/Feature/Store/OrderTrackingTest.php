<?php

namespace Tests\Feature\Store;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(string $number, string $email, string $phone): Order
    {
        return Order::query()->create([
            'order_number' => $number,
            'user_id' => null,
            'guest_email' => $email,
            'guest_token' => null,
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
            'payment_gateway' => 'cod',
            'coupon_id' => null,
            'subtotal' => '100.00',
            'discount_total' => '0',
            'shipping_total' => '200.00',
            'cod_fee' => '0',
            'grand_total' => '300.00',
            'shipping_address_snapshot' => [
                'full_name' => 'Test Buyer',
                'phone' => $phone,
                'line1' => 'Street 1',
                'city' => 'Lahore',
            ],
            'billing_address_snapshot' => [
                'full_name' => 'Test Buyer',
                'phone' => $phone,
                'line1' => 'Street 1',
                'city' => 'Lahore',
            ],
            'customer_notes' => null,
        ]);
    }

    public function test_tracks_by_order_reference_only(): void
    {
        $order = $this->createOrder('TR-REF001', 'one@example.com', '03001234567');

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_variant_id' => null,
            'product_name' => 'Test Runner',
            'variant_label' => 'Black',
            'sku' => 'RUN-01',
            'size_label' => 'UK 9',
            'quantity' => 1,
            'unit_price' => '100.00',
            'line_total' => '100.00',
        ]);

        Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'cod',
            'status' => PaymentStatus::Pending,
            'amount' => $order->grand_total,
            'external_id' => null,
            'meta' => [],
        ]);

        $this->post(route('store.order-tracking.lookup'), [
            'lookup_mode' => 'order_number',
            'order_number' => 'TR-REF001',
        ])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('result.order_number', 'TR-REF001')
                ->has('result.shipments')
                ->has('result.items', 1)
                ->where('result.items.0.product_name', 'Test Runner')
                ->has('result.payment.gateway_label')
                ->has('result.totals.grand_total')
                ->has('result.payments', 1)
                ->where('choices', []));
    }

    public function test_email_with_multiple_orders_returns_choices(): void
    {
        $this->createOrder('TR-MULTI1', 'shared@example.com', '03001111111');
        $this->createOrder('TR-MULTI2', 'shared@example.com', '03002222222');

        $this->post(route('store.order-tracking.lookup'), [
            'lookup_mode' => 'email',
            'email' => 'shared@example.com',
        ])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('result', null)
                ->has('choices', 2)
                ->where('choices.0.order_number', fn ($n) => in_array($n, ['TR-MULTI1', 'TR-MULTI2'], true))
                ->where('choices.1.order_number', fn ($n) => in_array($n, ['TR-MULTI1', 'TR-MULTI2'], true)));
    }

    public function test_phone_with_single_order_returns_result(): void
    {
        $this->createOrder('TR-PHONE1', 'solo@example.com', '03009998877');

        $this->post(route('store.order-tracking.lookup'), [
            'lookup_mode' => 'phone',
            'phone' => '03009998877',
        ])
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('result.order_number', 'TR-PHONE1')
                ->where('choices', []));
    }

    public function test_requires_active_tab_field(): void
    {
        $this->post(route('store.order-tracking.lookup'), [
            'lookup_mode' => 'order_number',
            'order_number' => '',
        ])->assertSessionHasErrors(['order_number']);
    }
}
