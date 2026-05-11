<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGatewayInterface;
use App\Domain\Payments\PaymentCallbackResult;
use App\Domain\Payments\PaymentInitResult;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

class CodGateway implements PaymentGatewayInterface
{
    public function code(): string
    {
        return 'cod';
    }

    public function initiate(Order $order, Payment $payment): PaymentInitResult
    {
        $payment->meta = array_merge($payment->meta ?? [], [
            'note' => 'Cash on delivery — pay the courier when your order arrives.',
        ]);
        $payment->save();

        return new PaymentInitResult(
            immediateSuccess: true,
            redirectUrl: null,
            meta: ['message' => 'You will pay cash when your order is delivered.'],
        );
    }

    public function redirectFormFields(Payment $payment): array
    {
        return [];
    }

    public function redirectSubmitUrl(): string
    {
        return '';
    }

    public function verifyCallback(Request $request): bool
    {
        return false;
    }

    public function parseCallback(Request $request): PaymentCallbackResult
    {
        return new PaymentCallbackResult(false, null, null, 'COD does not use callbacks');
    }
}
