<?php

namespace App\Domain\Payments;

use App\Domain\Payments\Contracts\PaymentGatewayInterface;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethodConfig;
use App\Models\PaymentSiteSetting;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PaymentCoordinator
{
    public function __construct(
        private readonly PaymentGatewayRegistry $registry,
        private readonly PaymentStatusRecorder $recorder,
    ) {}

    /**
     * Assert registry + DB admin toggle + config kill-switch.
     */
    public function assertGatewayAvailable(string $gatewayCode): void
    {
        try {
            $this->registry->get($gatewayCode);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException('This payment method is not available.');
        }

        $cfg = PaymentMethodConfig::query()->where('gateway_code', $gatewayCode)->first();
        if ($cfg === null || ! $cfg->enabled) {
            throw new InvalidArgumentException('This payment method is disabled.');
        }

        $gwConfig = config('payments.gateways.'.$gatewayCode);
        $kill = is_array($gwConfig) && array_key_exists('enabled', $gwConfig)
            ? (bool) $gwConfig['enabled']
            : true;
        if ($gatewayCode !== 'cod' && $kill === false) {
            throw new InvalidArgumentException('This payment method is temporarily unavailable.');
        }
    }

    public function initiateForOrder(Order $order, string $gatewayCode): PaymentInitResult
    {
        $this->assertGatewayAvailable($gatewayCode);

        /** @var PaymentGatewayInterface $gateway */
        $gateway = $this->registry->get($gatewayCode);

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => $gatewayCode,
            'status' => PaymentStatus::Pending,
            'amount' => $order->grand_total,
            'meta' => [],
        ]);

        $nextAttempt = (int) (PaymentAttempt::query()
            ->where('order_id', $order->id)
            ->max('attempt_number') ?? 0);

        $attempt = PaymentAttempt::query()->create([
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'gateway_code' => $gatewayCode,
            'attempt_number' => $nextAttempt + 1,
            'status' => 'initiated',
            'amount' => $order->grand_total,
            'request_snapshot' => ['phase' => 'initiate'],
        ]);

        try {
            $result = $gateway->initiate($order, $payment);
        } catch (\Throwable $e) {
            Log::warning('payment.initiate_failed', [
                'order_id' => $order->id,
                'gateway' => $gatewayCode,
                'error' => $e->getMessage(),
            ]);
            $attempt->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);
            $this->recorder->notePaymentTransition($payment, PaymentStatus::Failed, 'gateway', $e->getMessage());

            throw $e;
        }

        $attempt->update([
            'status' => $result->immediateSuccess ? 'completed' : 'redirecting',
            'external_reference' => $payment->fresh()->external_id,
            'request_snapshot' => array_merge($attempt->request_snapshot ?? [], [
                'init_meta' => $result->meta,
            ]),
        ]);

        return $result;
    }

    public function applyCodFallbackAfterOnlineFailure(Order $order, Payment $failedPayment, string $reason): void
    {
        $codFee = (string) config('store.cod_fee', '0');
        $shipping = (string) $order->shipping_total;
        $subtotal = (string) $order->subtotal;
        $discount = (string) $order->discount_total;
        $net = bcadd(bcsub($subtotal, $discount, 2), $shipping, 2);
        $grand = bcadd($net, $codFee, 2);

        $order->payment_gateway = 'cod';
        $order->cod_fee = $codFee;
        $order->grand_total = $grand;
        $order->payment_status = PaymentStatus::Pending;
        $order->save();

        $this->recorder->transitionOrderPayment(
            $order,
            PaymentStatus::Pending,
            'gateway',
            $failedPayment,
            'Order switched to cash on delivery after online payment failed.',
            ['fallback_from_gateway' => $failedPayment->gateway, 'reason' => $reason],
        );

        $this->recorder->notePaymentTransition(
            $failedPayment,
            PaymentStatus::Failed,
            'gateway',
            $reason,
            ['fallback_to_cod' => true],
        );

        Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'cod',
            'status' => PaymentStatus::Pending,
            'amount' => $grand,
            'meta' => [
                'note' => 'Cash on delivery after failed online attempt',
                'prior_payment_id' => $failedPayment->id,
            ],
        ]);
    }

    public function shouldFallbackOnlineFailureToCod(): bool
    {
        return PaymentSiteSetting::current()->fallback_online_failed_to_cod;
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function finalizeSuccessfulOnlinePayment(Order $order, Payment $payment, ?string $txnRef, array $rawPayload): void
    {
        $attempt = PaymentAttempt::query()
            ->where('payment_id', $payment->id)
            ->latest('id')
            ->first();
        $attempt?->update([
            'status' => 'succeeded',
            'response_snapshot' => $rawPayload,
            'external_reference' => $txnRef ?? $attempt->external_reference,
        ]);

        $this->recorder->notePaymentTransition(
            $payment,
            PaymentStatus::Paid,
            'gateway',
            $txnRef !== null && $txnRef !== '' ? 'Payment confirmed (ref: '.$txnRef.')' : 'Payment confirmed.',
            ['txn' => $txnRef],
        );
        $this->recorder->transitionOrderPayment(
            $order,
            PaymentStatus::Paid,
            'gateway',
            $payment,
            'Online payment received.',
        );
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function finalizeFailedOnlinePayment(Order $order, Payment $payment, string $reason, array $rawPayload): void
    {
        $attempt = PaymentAttempt::query()
            ->where('payment_id', $payment->id)
            ->latest('id')
            ->first();
        $attempt?->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'response_snapshot' => $rawPayload,
        ]);

        if ($payment->status !== PaymentStatus::Failed) {
            $this->recorder->notePaymentTransition($payment, PaymentStatus::Failed, 'gateway', $reason, ['raw' => $rawPayload]);
        }

        if ($this->shouldFallbackOnlineFailureToCod()) {
            $this->applyCodFallbackAfterOnlineFailure($order, $payment, $reason);

            return;
        }

        $this->recorder->transitionOrderPayment($order, PaymentStatus::Failed, 'gateway', $payment, $reason);
    }
}
