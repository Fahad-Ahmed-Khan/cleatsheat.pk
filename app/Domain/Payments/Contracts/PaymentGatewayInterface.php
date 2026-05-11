<?php

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\PaymentCallbackResult;
use App\Domain\Payments\PaymentInitResult;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    public function code(): string;

    public function initiate(Order $order, Payment $payment): PaymentInitResult;

    /**
     * POST fields for the hosted payment page (may be empty when payment completes in-app e.g. COD).
     *
     * @return array<string, string>
     */
    public function redirectFormFields(Payment $payment): array;

    /**
     * Absolute URL of the acquirer`s hosted form endpoint.
     */
    public function redirectSubmitUrl(): string;

    /**
     * Validate callback / IPN authenticity (HMAC, merchant checksum, etc.).
     */
    public function verifyCallback(Request $request): bool;

    /**
     * Map gateway-specific payload to a normalized result.
     */
    public function parseCallback(Request $request): PaymentCallbackResult;
}
