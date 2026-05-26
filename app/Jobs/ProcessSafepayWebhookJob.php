<?php

namespace App\Jobs;

use App\Domain\Payments\PaymentCoordinator;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Finalises a Safepay payment based on a verified webhook event.
 *
 * Safepay may retry the same event multiple times, and the browser callback might
 * already have completed the same transition. The job is therefore strictly
 * idempotent: if the matching order is already paid (or the matching payment
 * already failed), the corresponding finalisation call is skipped.
 */
class ProcessSafepayWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60, 120, 300];

    /**
     * @param  array<string, mixed>  $event
     */
    public function __construct(
        public readonly string $type,
        public readonly array $event,
    ) {}

    public function handle(PaymentCoordinator $coordinator): void
    {
        $tracker = $this->extractTrackerToken();
        if ($tracker === null) {
            Log::warning('payment.safepay.webhook.no_tracker', ['type' => $this->type]);

            return;
        }

        $payment = Payment::query()
            ->where('gateway', 'safepay')
            ->where('external_id', $tracker)
            ->latest('id')
            ->first();

        if ($payment === null) {
            Log::notice('payment.safepay.webhook.unknown_tracker', [
                'tracker' => $tracker,
                'type' => $this->type,
            ]);

            return;
        }

        $order = Order::query()->find($payment->order_id);
        if ($order === null) {
            Log::warning('payment.safepay.webhook.order_missing', [
                'payment_id' => $payment->id,
                'tracker' => $tracker,
            ]);

            return;
        }

        $txnRef = $this->extractTransactionReference() ?? $tracker;

        match ($this->type) {
            'payment.succeeded' => $this->finaliseSuccess($coordinator, $order, $payment, $txnRef),
            'payment.failed' => $this->finaliseFailure($coordinator, $order, $payment),
            default => Log::info('payment.safepay.webhook.ignored', [
                'type' => $this->type,
                'tracker' => $tracker,
            ]),
        };
    }

    private function finaliseSuccess(PaymentCoordinator $coordinator, Order $order, Payment $payment, string $txnRef): void
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return;
        }

        $coordinator->finalizeSuccessfulOnlinePayment(
            $order->refresh(),
            $payment->refresh(),
            $txnRef,
            $this->event,
        );
    }

    private function finaliseFailure(PaymentCoordinator $coordinator, Order $order, Payment $payment): void
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return;
        }

        if ($payment->status === PaymentStatus::Failed && $order->payment_status !== PaymentStatus::Pending) {
            return;
        }

        $reason = $this->extractFailureReason() ?? 'Payment failed at Safepay.';

        $coordinator->finalizeFailedOnlinePayment(
            $order->refresh(),
            $payment->refresh(),
            $reason,
            $this->event,
        );
    }

    private function extractTrackerToken(): ?string
    {
        $data = $this->event['data'] ?? [];
        if (! is_array($data)) {
            return null;
        }

        $candidates = [
            $data['tracker']['token'] ?? null,
            $data['tracker'] ?? null,
            $data['token'] ?? null,
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && str_starts_with($value, 'track_')) {
                return $value;
            }
        }

        return null;
    }

    private function extractTransactionReference(): ?string
    {
        $data = $this->event['data'] ?? [];
        if (! is_array($data)) {
            return null;
        }

        $candidates = [
            $data['action']['token'] ?? null,
            $data['transaction']['token'] ?? null,
            $data['transaction_id'] ?? null,
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function extractFailureReason(): ?string
    {
        $data = $this->event['data'] ?? [];
        if (! is_array($data)) {
            return null;
        }

        $candidates = [
            $data['error']['message'] ?? null,
            $data['failure_reason'] ?? null,
            $data['message'] ?? null,
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
