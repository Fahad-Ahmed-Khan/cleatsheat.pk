<?php

namespace App\Domain\Payments;

use App\Models\Order;

final class CheckoutPlacementResult
{
    public function __construct(
        public readonly Order $order,
        public readonly ?PaymentInitResult $paymentInit,
    ) {}
}
