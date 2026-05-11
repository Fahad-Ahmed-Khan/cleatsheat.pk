<?php

namespace App\Domain\Payments;

final class PaymentInitResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly bool $immediateSuccess,
        public readonly ?string $redirectUrl = null,
        public readonly array $meta = [],
    ) {}
}
