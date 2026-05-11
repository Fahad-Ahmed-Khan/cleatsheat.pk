<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JazzCashWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $orderNumber = $request->input('order_number');
        $paid = $request->boolean('paid');

        if (! $orderNumber || ! $paid) {
            return response()->json(['success' => false, 'message' => 'Invalid payload'], 422);
        }

        $order = Order::query()->where('order_number', $orderNumber)->firstOrFail();

        DB::transaction(function () use ($order): void {
            $payment = Payment::query()
                ->where('order_id', $order->id)
                ->where('gateway', 'jazzcash')
                ->latest()
                ->first();

            if ($payment) {
                $payment->update([
                    'status' => PaymentStatus::Paid,
                    'paid_at' => now(),
                    'meta' => array_merge($payment->meta ?? [], ['webhook' => 'jazzcash_stub']),
                ]);
            }

            $order->update([
                'payment_status' => PaymentStatus::Paid,
            ]);
        });

        return response()->json(['success' => true]);
    }
}
