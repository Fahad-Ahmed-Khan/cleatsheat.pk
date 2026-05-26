<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\Safepay\SafepayClientFactory;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentSiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Safepay\SafepayClient;
use Tests\TestCase;

class SafepayCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makePendingSafepayOrder(string $tracker = 'track_test_001'): array
    {
        $order = Order::query()->create([
            'order_number' => 'TR-SFTEST001',
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

    /**
     * Bind a stand-in SafepayClientFactory whose reporter->retrieve() returns the supplied
     * tracker payload, or throws when $tracker is null.
     *
     * @param  array<string, mixed>|null  $tracker
     */
    private function fakeSafepayClient(?array $tracker): void
    {
        $reporter = new class($tracker)
        {
            public function __construct(private readonly ?array $tracker) {}

            public function retrieve(string $token): mixed
            {
                if ($this->tracker === null) {
                    throw new RuntimeException('Safepay reporter unavailable.');
                }

                // Mirror the SDK shape: `reporter->retrieve()` returns the inner tracker
                // payload directly (BaseSafepayClient strips the outer `data` wrapper).
                return json_decode(json_encode($this->tracker, JSON_THROW_ON_ERROR), false);
            }
        };

        // Extend the real SafepayClient so we satisfy the factory's return-type hint while
        // routing the only service we exercise (reporter) at our stub.
        $client = new class(['api_key' => 'sk_test', 'api_base' => 'https://sandbox.api.getsafepay.com']) extends SafepayClient
        {
            public ?object $stubReporter = null;

            public function __get($name)
            {
                if ($name === 'reporter' && $this->stubReporter !== null) {
                    return $this->stubReporter;
                }

                return parent::__get($name);
            }
        };
        $client->stubReporter = $reporter;

        $factory = Mockery::mock(SafepayClientFactory::class);
        $factory->shouldReceive('make')->andReturn($client);
        $factory->shouldReceive('environment')->andReturn('sandbox');
        $factory->shouldReceive('apiBase')->andReturn('https://sandbox.api.getsafepay.com');

        $this->app->instance(SafepayClientFactory::class, $factory);
    }

    public function test_safepay_success_marks_order_paid(): void
    {
        [$order, $payment] = $this->makePendingSafepayOrder('track_ok_1');

        $this->fakeSafepayClient([
            'token' => 'track_ok_1',
            'state' => 'TRACKER_ENDED',
            'metadata' => [
                'order_id' => ['key' => 'order_id', 'value' => $order->order_number],
            ],
        ]);

        $response = $this->get(route('payments.callback', ['gateway' => 'safepay']).'?tracker=track_ok_1');

        $response->assertRedirect(route('store.checkout.thankyou'));
        $this->assertEquals(PaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertEquals(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_safepay_callback_without_tracker_is_rejected(): void
    {
        [$order] = $this->makePendingSafepayOrder();

        $this->fakeSafepayClient(null);

        $response = $this->get(route('payments.callback', ['gateway' => 'safepay']));

        $response->assertRedirect(route('store.home'));
        $this->assertEquals(PaymentStatus::Pending, $order->fresh()->payment_status);
    }

    public function test_safepay_callback_is_idempotent_when_order_already_paid(): void
    {
        [$order, $payment] = $this->makePendingSafepayOrder('track_dup_1');
        $order->update(['payment_status' => PaymentStatus::Paid]);
        $payment->update(['status' => PaymentStatus::Paid]);

        $this->fakeSafepayClient([
            'token' => 'track_dup_1',
            'state' => 'TRACKER_ENDED',
            'metadata' => [
                'order_id' => ['key' => 'order_id', 'value' => $order->order_number],
            ],
        ]);

        $response = $this->get(route('payments.callback', ['gateway' => 'safepay']).'?tracker=track_dup_1');

        $response->assertRedirect(route('store.checkout.thankyou'));
        $this->assertEquals(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    public function test_safepay_failure_with_fallback_switches_to_cod(): void
    {
        PaymentSiteSetting::query()->updateOrCreate(
            ['id' => 1],
            ['fallback_online_failed_to_cod' => true],
        );
        config(['store.cod_fee' => '50']);

        [$order, $payment] = $this->makePendingSafepayOrder('track_fail_1');

        $this->fakeSafepayClient([
            'token' => 'track_fail_1',
            'state' => 'TRACKER_FAILED',
            'metadata' => [
                'order_id' => ['key' => 'order_id', 'value' => $order->order_number],
            ],
        ]);

        $response = $this->get(route('payments.callback', ['gateway' => 'safepay']).'?tracker=track_fail_1');

        $response->assertRedirect(route('store.checkout.thankyou'));

        $fresh = $order->fresh();
        $this->assertSame('cod', $fresh->payment_gateway);
        $this->assertEquals(PaymentStatus::Pending, $fresh->payment_status);
        $this->assertEquals('50.00', (string) $fresh->cod_fee);
        $this->assertEquals(PaymentStatus::Failed, $payment->fresh()->status);
    }
}
