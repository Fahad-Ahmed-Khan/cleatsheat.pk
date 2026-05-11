<?php

namespace App\Domain\Bargain;

use App\Enums\BargainSessionState;
use App\Models\BargainSession;
use App\Models\User;
use App\Support\Bargain\PhoneNormalizer;

final class PriceLockResolver
{
    public function lockedUnitPrice(int $variantId, ?User $user, ?string $normalizedPhone): ?string
    {
        if ($user === null && $normalizedPhone === null) {
            return null;
        }

        $key = $user !== null
            ? PhoneNormalizer::customerKey($user, null)
            : PhoneNormalizer::customerKey(null, $normalizedPhone);

        $session = BargainSession::query()
            ->where('product_variant_id', $variantId)
            ->where('customer_key', $key)
            ->where('state', BargainSessionState::Accepted)
            ->whereNotNull('accepted_price')
            ->whereNull('lock_consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if ($session === null) {
            return null;
        }

        return (string) $session->accepted_price;
    }

    /**
     * @param  array<int, int>  $variantIds
     */
    public function consumeLocksForCheckout(?User $user, ?string $normalizedPhone, array $variantIds): void
    {
        if ($variantIds === []) {
            return;
        }

        if ($user === null && $normalizedPhone === null) {
            return;
        }

        $key = $user !== null
            ? PhoneNormalizer::customerKey($user, null)
            : PhoneNormalizer::customerKey(null, $normalizedPhone);

        BargainSession::query()
            ->where('customer_key', $key)
            ->whereIn('product_variant_id', $variantIds)
            ->where('state', BargainSessionState::Accepted)
            ->whereNull('lock_consumed_at')
            ->update([
                'lock_consumed_at' => now(),
                'state' => BargainSessionState::Consumed,
                'checkout_token' => null,
            ]);
    }
}
