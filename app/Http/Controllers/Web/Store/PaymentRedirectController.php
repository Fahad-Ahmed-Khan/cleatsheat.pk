<?php

namespace App\Http\Controllers\Web\Store;

use App\Domain\Payments\PaymentGatewayRegistry;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Crypt;

class PaymentRedirectController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayRegistry $registry,
    ) {}

    public function form(string $gateway, string $token): View
    {
        $gateway = strtolower($gateway);
        $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);

        if (($payload['exp'] ?? 0) < now()->timestamp) {
            abort(403, 'Payment session expired. Please place your order again.');
        }

        $payment = Payment::query()->findOrFail((int) $payload['payment_id']);

        if ($payment->gateway !== $gateway) {
            abort(403);
        }

        $gw = $this->registry->get($gateway);
        $submitUrl = $gw->redirectSubmitUrl();
        if ($submitUrl === '') {
            abort(500, 'Payment gateway is not configured.');
        }

        $fields = $gw->redirectFormFields($payment);

        return view('payments.redirect', [
            'submitUrl' => $submitUrl,
            'fields' => $fields,
        ]);
    }
}
