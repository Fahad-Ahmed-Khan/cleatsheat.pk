<?php

namespace App\Domain\Payments;

/**
 * Normalized result after parsing a gateway callback (return URL or IPN).
 *
 * @phpstan-type Raw array<string, mixed>
 */
final class PaymentCallbackResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $orderNumber,
        public readonly ?string $transactionReference,
        public readonly ?string $failureReason,
        public readonly array $raw = [],
    ) {}
}
