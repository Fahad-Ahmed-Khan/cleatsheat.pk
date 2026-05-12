<?php

namespace App\Domain\Bargain;

use App\Enums\BargainSessionState;
use App\Models\BargainSession;
use App\Models\User;
use App\Support\Bargain\PhoneNormalizer;

final class PriceLockResolver
{
    /**
     * Storefront bargain JSON uses Bearer-only user resolution, so sessions are usually keyed by phone
     * (`p:+92…`) even when the customer is logged in on the web shop (`u:{id}`). Cart add must match both.
     *
     * @return list<string>
     */
    private function customerKeysForLock(?User $user, ?string $normalizedPhone): array
    {
        $keys = [];
        if ($normalizedPhone !== null) {
            $keys[] = PhoneNormalizer::customerKey(null, $normalizedPhone);
        }
        if ($user !== null) {
            $keys[] = PhoneNormalizer::customerKey($user, null);
        }

        return array_values(array_unique($keys, SORT_STRING));
    }

    public function lockForCart(int $variantId, ?User $user, ?string $normalizedPhone): ?BargainSession
    {
        $keys = $this->customerKeysForLock($user, $normalizedPhone);
        if ($keys === []) {
            return null;
        }

        return BargainSession::query()
            ->where('product_variant_id', $variantId)
            ->whereIn('customer_key', $keys)
            ->where('state', BargainSessionState::Accepted)
            ->whereNotNull('accepted_price')
            ->whereNull('lock_consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();
    }

    public function lockedUnitPrice(int $variantId, ?User $user, ?string $normalizedPhone): ?string
    {
        $session = $this->lockForCart($variantId, $user, $normalizedPhone);

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

        $keys = $this->customerKeysForLock($user, $normalizedPhone);
        if ($keys === []) {
            return;
        }

        BargainSession::query()
            ->whereIn('customer_key', $keys)
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
