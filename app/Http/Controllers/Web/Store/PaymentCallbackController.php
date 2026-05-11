<?php

namespace App\Http\Controllers\Web\Store;

use App\Domain\Payments\PaymentCoordinator;
use App\Domain\Payments\PaymentGatewayRegistry;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayRegistry $registry,
        private readonly PaymentCoordinator $coordinator,
    ) {}

    public function callback(Request $request, string $gateway): RedirectResponse
    {
        $gateway = strtolower($gateway);

        try {
            $gw = $this->registry->get($gateway);
        } catch (\InvalidArgumentException) {
            abort(404);
        }

        if (! $gw->verifyCallback($request)) {
            Log::warning('payment.callback_bad_signature', ['gateway' => $gateway]);

            return redirect()
                ->route('store.home')
                ->with('flash_payment_error', 'We could not verify your payment response. If money was deducted, contact support with your order reference.');
        }

        $parsed = $gw->parseCallback($request);

        if ($parsed->orderNumber === null || $parsed->orderNumber === '') {
            return redirect()
                ->route('store.home')
                ->with('flash_payment_error', 'Payment response was incomplete. Please contact support.');
        }

        $order = Order::query()->where('order_number', $parsed->orderNumber)->first();
        if ($order === null) {
            return redirect()
                ->route('store.home')
                ->with('flash_payment_error', 'No matching order was found for this payment.');
        }

        if ($order->payment_status === PaymentStatus::Paid) {
            $request->session()->flash('thank_you_order_id', $order->id);

            return redirect()->route('store.checkout.thankyou');
        }

        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->where('gateway', $gateway)
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        if ($payment === null) {
            Log::notice('payment.callback_no_pending', ['order_id' => $order->id, 'gateway' => $gateway]);

            return redirect()
                ->route('store.home')
                ->with('flash_payment_error', 'There is no pending payment for this order. If you need help, contact support with reference '.$order->order_number.'.');
        }

        if ($parsed->success) {
            $this->coordinator->finalizeSuccessfulOnlinePayment(
                $order->fresh(),
                $payment->fresh(),
                $parsed->transactionReference,
                $parsed->raw,
            );

            $request->session()->flash('thank_you_order_id', $order->id);
            $request->session()->flash('payment_notice', 'Your payment was received successfully.');

            return redirect()->route('store.checkout.thankyou');
        }

        $reason = $parsed->failureReason ?? 'Payment was not completed.';
        $this->coordinator->finalizeFailedOnlinePayment(
            $order->fresh(),
            $payment->fresh(),
            $reason,
            $parsed->raw,
        );

        $orderFresh = $order->fresh();
        if ($orderFresh && $orderFresh->payment_gateway === 'cod') {
            $request->session()->flash('thank_you_order_id', $orderFresh->id);
            $request->session()->flash('payment_notice', 'Online payment did not complete. Your order is now set to cash on delivery.');

            return redirect()->route('store.checkout.thankyou');
        }

        return redirect()
            ->route('store.home')
            ->with('flash_payment_error', $reason.' Reference: '.$order->order_number);
    }
}
