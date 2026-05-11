<?php

namespace App\Domain\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentStatusHistory;

class PaymentStatusRecorder
{
    public function transitionOrderPayment(
        Order $order,
        PaymentStatus $to,
        string $source,
        ?Payment $payment = null,
        ?string $message = null,
        ?array $meta = null,
        bool $force = false,
    ): void {
        $from = $order->payment_status;
        if (! $force && $from === $to) {
            return;
        }

        $order->payment_status = $to;
        $order->save();

        PaymentStatusHistory::query()->create([
            'order_id' => $order->id,
            'payment_id' => $payment?->id,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'source' => $source,
            'message' => $message,
            'meta' => $meta,
        ]);
    }

    public function notePaymentTransition(
        Payment $payment,
        PaymentStatus $to,
        string $source,
        ?string $message = null,
        ?array $meta = null,
        bool $force = false,
    ): void {
        $from = $payment->status;
        if (! $force && $from === $to) {
            return;
        }

        $payment->status = $to;
        if ($to === PaymentStatus::Paid) {
            $payment->paid_at = now();
        }
        $payment->save();

        PaymentStatusHistory::query()->create([
            'order_id' => $payment->order_id,
            'payment_id' => $payment->id,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'source' => $source,
            'message' => $message,
            'meta' => $meta,
        ]);
    }
}
