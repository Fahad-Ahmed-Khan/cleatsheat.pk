<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\ProcessSafepayWebhookJob;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentSiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SafepayWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function makePendingOrderWithTracker(string $tracker): array
    {
        $order = Order::query()->create([
            'order_number' => 'TR-WHTEST001',
            'user_id' => null,
            'guest_email' => 'buyer@example.com',
            'guest_token' => null,
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
            'payment_gateway' => 'safepay',
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
            'gateway' => 'safepay',
            'status' => PaymentStatus::Pending,
            'amount' => $order->grand_total,
            'external_id' => $tracker,
            'meta' => ['tracker_token' => $tracker],
        ]);

        return [$order, $payment];
    }

    private function signedPostJson(string $url, array $body, string $secret): \Illuminate\Testing\TestResponse
    {
        $payload = json_encode($body, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha512', $payload, $secret);

        return $this->call(
            'POST',
            $url,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SFPY_SIGNATURE' => $signature,
                'HTTP_ACCEPT' => 'application/json',
            ],
            $payload,
        );
    }

    public function test_webhook_rejects_request_without_signature(): void
    {
        config(['payments.gateways.safepay.webhook_secret' => 'whsec_test']);

        $response = $this->postJson(route('webhooks.safepay'), ['type' => 'payment.succeeded']);

        $response->assertStatus(400);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config(['payments.gateways.safepay.webhook_secret' => 'whsec_test']);

        $response = $this->call(
            'POST',
            route('webhooks.safepay'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SFPY_SIGNATURE' => 'definitely-not-valid',
            ],
            json_encode(['type' => 'payment.succeeded'], JSON_THROW_ON_ERROR),
        );

        $response->assertStatus(400);
    }

    public function test_webhook_returns_503_when_secret_missing(): void
    {
        config(['payments.gateways.safepay.webhook_secret' => '']);

        $response = $this->postJson(route('webhooks.safepay'), ['type' => 'payment.succeeded']);

        $response->assertStatus(503);
    }

    public function test_valid_succeeded_webhook_dispatches_job(): void
    {
        Bus::fake();
        config(['payments.gateways.safepay.webhook_secret' => 'whsec_test']);

        $body = [
            'type' => 'payment.succeeded',
            'data' => [
                'tracker' => ['token' => 'track_w_1', 'state' => 'TRACKER_ENDED'],
            ],
        ];

        $response = $this->signedPostJson(route('webhooks.safepay'), $body, 'whsec_test');

        $response->assertStatus(200);
        Bus::assertDispatched(ProcessSafepayWebhookJob::class, function (ProcessSafepayWebhookJob $job) {
            return $job->type === 'payment.succeeded'
                && ($job->event['data']['tracker']['token'] ?? null) === 'track_w_1';
        });
    }

    public function test_succeeded_webhook_marks_order_paid(): void
    {
        config(['payments.gateways.safepay.webhook_secret' => 'whsec_test']);

        [$order, $payment] = $this->makePendingOrderWithTracker('track_w_2');

        $body = [
            'type' => 'payment.succeeded',
            'data' => [
                'tracker' => ['token' => 'track_w_2', 'state' => 'TRACKER_ENDED'],
                'action' => ['token' => 'req_abc123'],
            ],
        ];

        $response = $this->signedPostJson(route('webhooks.safepay'), $body, 'whsec_test');

        $response->assertStatus(200);
        $this->assertEquals(PaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertEquals(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_failed_webhook_with_fallback_switches_order_to_cod(): void
    {
        config(['payments.gateways.safepay.webhook_secret' => 'whsec_test']);
        config(['store.cod_fee' => '50']);
        PaymentSiteSetting::query()->updateOrCreate(
            ['id' => 1],
            ['fallback_online_failed_to_cod' => true],
        );

        [$order, $payment] = $this->makePendingOrderWithTracker('track_w_3');

        $body = [
            'type' => 'payment.failed',
            'data' => [
                'tracker' => ['token' => 'track_w_3', 'state' => 'TRACKER_FAILED'],
                'error' => ['message' => 'Card declined'],
            ],
        ];

        $response = $this->signedPostJson(route('webhooks.safepay'), $body, 'whsec_test');

        $response->assertStatus(200);
        $fresh = $order->fresh();
        $this->assertSame('cod', $fresh->payment_gateway);
        $this->assertEquals(PaymentStatus::Pending, $fresh->payment_status);
        $this->assertEquals(PaymentStatus::Failed, $payment->fresh()->status);
    }

    public function test_duplicate_succeeded_webhook_is_idempotent(): void
    {
        config(['payments.gateways.safepay.webhook_secret' => 'whsec_test']);

        [$order, $payment] = $this->makePendingOrderWithTracker('track_w_4');
        $order->update(['payment_status' => PaymentStatus::Paid]);
        $payment->update(['status' => PaymentStatus::Paid]);

        $body = [
            'type' => 'payment.succeeded',
            'data' => [
                'tracker' => ['token' => 'track_w_4', 'state' => 'TRACKER_ENDED'],
            ],
        ];

        $response = $this->signedPostJson(route('webhooks.safepay'), $body, 'whsec_test');

        $response->assertStatus(200);
        $this->assertEquals(PaymentStatus::Paid, $order->fresh()->payment_status);
    }
}
