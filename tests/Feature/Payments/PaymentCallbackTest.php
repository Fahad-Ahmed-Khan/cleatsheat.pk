<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\Support\SortedHmacSigner;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentSiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentCallbackTest extends TestCase
{
    use RefreshDatabase;

    private function makePendingEasypaisaOrder(): array
    {
        $order = Order::query()->create([
            'order_number' => 'TR-TESTPAY01',
            'user_id' => null,
            'guest_email' => 'buyer@example.com',
            'guest_token' => null,
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
            'payment_gateway' => 'easypaisa',
            'coupon_id' => null,
            'subtotal' => '100.00',
            'discount_total' => '0',
            'shipping_total' => '200.00',
            'cod_fee' => '0',
            'grand_total' => '300.00',
            'shipping_address_snapshot' => [
                'full_name' => 'Test', 'phone' => '1', 'line1' => 'L', 'city' => 'Karachi',
            ],
            'billing_address_snapshot' => [
                'full_name' => 'Test', 'phone' => '1', 'line1' => 'L', 'city' => 'Karachi',
            ],
            'customer_notes' => null,
        ]);

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'easypaisa',
            'status' => PaymentStatus::Pending,
            'amount' => $order->grand_total,
            'external_id' => 'ep_test',
            'meta' => [],
        ]);

        return [$order, $payment];
    }

    public function test_easypaisa_callback_rejects_invalid_signature(): void
    {
        config(['payments.gateways.easypaisa.hash_key' => 'secret-key']);

        [$order] = $this->makePendingEasypaisaOrder();

        $response = $this->post(route('payments.callback', ['gateway' => 'easypaisa']), [
            'order_ref' => $order->order_number,
            'resp_code' => '000',
            'secure_hash' => 'wrong',
        ]);

        $response->assertRedirect(route('store.home'));
        $this->assertNotEquals(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    public function test_easypaisa_success_marks_order_paid(): void
    {
        config(['payments.gateways.easypaisa.hash_key' => 'secret-key']);

        [$order, $payment] = $this->makePendingEasypaisaOrder();

        $fields = [
            'order_ref' => $order->order_number,
            'resp_code' => '000',
            'transaction_id' => 'TXN-OK-1',
        ];
        $fields['secure_hash'] = SortedHmacSigner::sign($fields, 'secure_hash', 'secret-key');

        $response = $this->post(route('payments.callback', ['gateway' => 'easypaisa']), $fields);

        $response->assertRedirect(route('store.checkout.thankyou'));
        $this->assertEquals(PaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertEquals(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_easypaisa_failure_with_fallback_switches_to_cod(): void
    {
        config(['payments.gateways.easypaisa.hash_key' => 'secret-key']);
        config(['store.cod_fee' => '50']);

        PaymentSiteSetting::query()->updateOrCreate(
            ['id' => 1],
            ['fallback_online_failed_to_cod' => true],
        );

        [$order, $payment] = $this->makePendingEasypaisaOrder();

        $fields = [
            'order_ref' => $order->order_number,
            'resp_code' => '999',
            'payment_status' => 'failed',
        ];
        $fields['secure_hash'] = SortedHmacSigner::sign($fields, 'secure_hash', 'secret-key');

        $response = $this->post(route('payments.callback', ['gateway' => 'easypaisa']), $fields);

        $response->assertRedirect(route('store.checkout.thankyou'));
        $fresh = $order->fresh();
        $this->assertSame('cod', $fresh->payment_gateway);
        $this->assertEquals(PaymentStatus::Pending, $fresh->payment_status);
        $this->assertEquals('50.00', (string) $fresh->cod_fee);
        $this->assertEquals(PaymentStatus::Failed, $payment->fresh()->status);
    }

    public function test_easypaisa_failure_without_fallback_marks_failed(): void
    {
        config(['payments.gateways.easypaisa.hash_key' => 'secret-key']);

        PaymentSiteSetting::query()->updateOrCreate(
            ['id' => 1],
            ['fallback_online_failed_to_cod' => false],
        );

        [$order, $payment] = $this->makePendingEasypaisaOrder();

        $fields = [
            'order_ref' => $order->order_number,
            'resp_code' => '999',
            'payment_status' => 'failed',
        ];
        $fields['secure_hash'] = SortedHmacSigner::sign($fields, 'secure_hash', 'secret-key');

        $response = $this->post(route('payments.callback', ['gateway' => 'easypaisa']), $fields);

        $response->assertRedirect(route('store.home'));
        $this->assertEquals(PaymentStatus::Failed, $order->fresh()->payment_status);
        $this->assertEquals(PaymentStatus::Failed, $payment->fresh()->status);
    }
}
