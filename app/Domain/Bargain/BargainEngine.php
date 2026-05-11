<?php

namespace App\Domain\Bargain;

use App\Enums\BargainSessionState;
use App\Models\BargainMessage;
use App\Models\BargainSession;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Bargain\OfferExtractor;
use App\Support\Bargain\PhoneNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BargainEngine
{
    public function __construct(
        private readonly OpenAiBargainPolisher $polisher,
    ) {}

    public function start(int $variantId, ?User $user, ?string $guestToken, string $customerPhone, string $customerName): BargainSession
    {
        if (! (bool) config('bargain.enabled', true)) {
            throw new \InvalidArgumentException('Bargaining is currently disabled.');
        }

        $normalizedPhone = PhoneNormalizer::normalize($customerPhone);
        if ($normalizedPhone === null) {
            throw new \InvalidArgumentException('Please provide a valid phone number.');
        }

        $customerKey = PhoneNormalizer::customerKey($user, $normalizedPhone);

        $customerName = trim($customerName);
        if ($customerName === '') {
            throw new \InvalidArgumentException('Please provide your name.');
        }

        return DB::transaction(function () use ($variantId, $user, $guestToken, $normalizedPhone, $customerKey, $customerName): BargainSession {
            /** @var ProductVariant $variant */
            $variant = ProductVariant::query()
                ->whereKey($variantId)
                ->lockForUpdate()
                ->firstOrFail();

            $variant->loadMissing(['product', 'color']);

            $policy = BargainPolicy::fromVariant($variant);
            if (! $policy->bargainEnabled) {
                throw new \InvalidArgumentException('Bargaining is not available for this product right now.');
            }

            BargainSession::query()
                ->where('customer_key', $customerKey)
                ->where('product_variant_id', $variant->id)
                ->whereIn('state', [BargainSessionState::Open->value, BargainSessionState::Countered->value])
                ->update(['state' => BargainSessionState::Expired]);

            $session = BargainSession::query()->create([
                'user_id' => $user?->id,
                'guest_token' => $guestToken,
                'customer_phone' => $normalizedPhone,
                'customer_name' => $customerName,
                'customer_key' => $customerKey,
                'product_variant_id' => $variant->id,
                'state' => BargainSessionState::Open,
                'list_price' => $policy->listPrice,
                'current_offer' => null,
                'accepted_price' => null,
                'checkout_token' => null,
                'lock_consumed_at' => null,
                'expires_at' => now()->addMinutes((int) config('bargain.session_ttl_minutes', 30)),
            ]);

            $label = $this->variantLabel($variant);
            $base = DeterministicShopkeeperReply::welcome($customerName, $variant->product->name, $label, $policy->listPrice);
            $text = $this->polisher->polishShopkeeperWithContext(
                draftText: $base,
                contextMessages: [],
                facts: [
                    'list_price' => $policy->listPrice,
                    'current_offer' => null,
                    'last_customer_offer' => null,
                    'customer_name' => $customerName,
                    'product_name' => (string) $variant->product->name,
                    'variant_label' => $label,
                    'color_name' => (string) ($variant->color?->name ?? ''),
                ],
                languageHint: 'roman_urdu',
            );

            BargainMessage::query()->create([
                'bargain_session_id' => $session->id,
                'role' => 'assistant',
                'body' => $text,
                'meta' => [
                    'kind' => 'welcome',
                    'list_price' => $policy->listPrice,
                ],
            ]);

            return $session->fresh(['messages']);
        });
    }

    public function sendMessage(BargainSession $session, ?User $user, string $customerPhone, string $message): BargainMessage
    {
        $this->authorize($session, $user, $customerPhone);
        $this->syncExpiry($session);

        if (! in_array($session->state, [BargainSessionState::Open, BargainSessionState::Countered], true)) {
            throw new \InvalidArgumentException('This negotiation is no longer active.');
        }

        return DB::transaction(function () use ($session, $message): BargainMessage {
            /** @var BargainSession $session */
            $session = BargainSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            $this->syncExpiry($session);
            if (! in_array($session->state, [BargainSessionState::Open, BargainSessionState::Countered], true)) {
                throw new \InvalidArgumentException('This negotiation is no longer active.');
            }

            /** @var ProductVariant $variant */
            $variant = ProductVariant::query()->whereKey($session->product_variant_id)->lockForUpdate()->firstOrFail();
            $variant->loadMissing(['product', 'color']);
            $policy = BargainPolicy::fromVariant($variant);
            if (! $policy->bargainEnabled) {
                throw new \InvalidArgumentException('Bargaining is not available for this product right now.');
            }

            BargainMessage::query()->create([
                'bargain_session_id' => $session->id,
                'role' => 'customer',
                'body' => $message,
                'meta' => [
                    'parsed_offer' => OfferExtractor::extractPkrAmount($message),
                ],
            ]);

            $parsed = OfferExtractor::extractPkrAmount($message);
            if ($parsed === null) {
                [$context, $hint, $lastCustomerOffer] = $this->buildAiContext($session->id);
                $base = $lastCustomerOffer !== null
                    ? DeterministicShopkeeperReply::nudgeIncreaseFromLastOffer($lastCustomerOffer)
                    : DeterministicShopkeeperReply::askForOfferWithAmount($policy->listPrice);
                $text = $this->polisher->polishShopkeeperWithContext(
                    draftText: $base,
                    contextMessages: $context,
                    facts: [
                        'list_price' => $policy->listPrice,
                        'current_offer' => $session->current_offer !== null ? (string) $session->current_offer : null,
                        'last_customer_offer' => $lastCustomerOffer,
                        'customer_name' => $session->customer_name ?? null,
                        'product_name' => (string) $variant->product->name,
                        'variant_label' => $this->variantLabel($variant),
                        'color_name' => (string) ($variant->color?->name ?? ''),
                    ],
                    languageHint: $hint,
                );

                return BargainMessage::query()->create([
                    'bargain_session_id' => $session->id,
                    'role' => 'assistant',
                    'body' => $text,
                    'meta' => [
                        'kind' => 'needs_amount',
                        'list_price' => $policy->listPrice,
                    ],
                ]);
            }

            // Compare the customer's stated amount to the floor before clamping up to min:
            // otherwise e.g. "PKR 850" would clamp to 900 and be treated as an acceptable offer.
            $stated = $parsed;
            if (bccomp($stated, $policy->listPrice, 2) === 1) {
                $stated = $policy->listPrice;
            }

            if (bccomp($stated, $policy->minAllowedPrice, 2) === -1) {
                $prevShopLine = $session->current_offer !== null ? (string) $session->current_offer : null;
                $lastCustomerId = (int) BargainMessage::query()
                    ->where('bargain_session_id', $session->id)
                    ->where('role', 'customer')
                    ->orderByDesc('id')
                    ->value('id');
                $seedMaterial = 'bargain:'.$session->id.':'.$lastCustomerId;

                $counter = $policy->steppedCounterBelowMin($prevShopLine, $seedMaterial);

                $session->current_offer = $counter;
                $session->state = BargainSessionState::Countered;
                $session->save();

                $base = DeterministicShopkeeperReply::counterTooLow($stated, $counter, $policy->listPrice);
                [$context, $hint, $lastCustomerOffer] = $this->buildAiContext($session->id);
                $text = $this->polisher->polishShopkeeperWithContext(
                    draftText: $base,
                    contextMessages: $context,
                    facts: [
                        'list_price' => $policy->listPrice,
                        'current_offer' => $counter,
                        'last_customer_offer' => $lastCustomerOffer,
                        'customer_name' => $session->customer_name ?? null,
                        'product_name' => (string) $variant->product->name,
                        'variant_label' => $this->variantLabel($variant),
                        'color_name' => (string) ($variant->color?->name ?? ''),
                    ],
                    languageHint: $hint,
                );

                return BargainMessage::query()->create([
                    'bargain_session_id' => $session->id,
                    'role' => 'assistant',
                    'body' => $text,
                    'meta' => [
                        'kind' => 'counter',
                        'customer_offer' => $stated,
                        'counter_offer' => $counter,
                        'list_price' => $policy->listPrice,
                    ],
                ]);
            }

            $offer = $policy->clampToAllowedRange($stated);
            $prevShopLine = $session->current_offer !== null ? (string) $session->current_offer : null;
            $nudged = $policy->nudgeInRangeStatedPrice($offer);

            if ($prevShopLine !== null && $prevShopLine !== '' && bccomp($prevShopLine, '0', 2) === 1) {
                // Hard rule: shop offers must never increase within a session.
                // This prevents the "you said 11600, then we replied 11767" frustration.
                if (bccomp($nudged, $prevShopLine, 2) === 1) {
                    $nudged = $prevShopLine;
                }

                // If the customer is already willing to meet/beat our last offer,
                // keep the best (lowest) shop offer on the table.
                if (bccomp($offer, $prevShopLine, 2) >= 0) {
                    $nudged = $prevShopLine;
                }
            }

            $session->current_offer = $nudged;
            $session->state = BargainSessionState::Countered;
            $session->save();

            $nudgeApplied = bccomp($nudged, $offer, 2) === 1;
            $base = $nudgeApplied
                ? DeterministicShopkeeperReply::acceptableNudged($offer, $nudged, $policy->listPrice)
                : DeterministicShopkeeperReply::acceptable($nudged);
            [$context, $hint, $lastCustomerOffer] = $this->buildAiContext($session->id);
            $text = $this->polisher->polishShopkeeperWithContext(
                draftText: $base,
                contextMessages: $context,
                facts: [
                    'list_price' => $policy->listPrice,
                    'current_offer' => $nudged,
                    'last_customer_offer' => $lastCustomerOffer,
                    'customer_name' => $session->customer_name ?? null,
                    'product_name' => (string) $variant->product->name,
                    'variant_label' => $this->variantLabel($variant),
                    'color_name' => (string) ($variant->color?->name ?? ''),
                ],
                languageHint: $hint,
            );

            return BargainMessage::query()->create([
                'bargain_session_id' => $session->id,
                'role' => 'assistant',
                'body' => $text,
                'meta' => [
                    'kind' => $nudgeApplied ? 'acceptable_nudge' : 'acceptable',
                    'customer_offer' => $offer,
                    'counter_offer' => $nudged,
                    'list_price' => $policy->listPrice,
                ],
            ]);
        });
    }

    public function accept(BargainSession $session, ?User $user, string $customerPhone, ?string $acceptedPrice): BargainSession
    {
        $this->authorize($session, $user, $customerPhone);
        $this->syncExpiry($session);

        if (! in_array($session->state, [BargainSessionState::Open, BargainSessionState::Countered], true)) {
            throw new \InvalidArgumentException('This negotiation can’t be accepted anymore.');
        }

        return DB::transaction(function () use ($session, $acceptedPrice): BargainSession {
            /** @var BargainSession $session */
            $session = BargainSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->syncExpiry($session);
            if (! in_array($session->state, [BargainSessionState::Open, BargainSessionState::Countered], true)) {
                throw new \InvalidArgumentException('This negotiation can’t be accepted anymore.');
            }

            /** @var ProductVariant $variant */
            $variant = ProductVariant::query()->whereKey($session->product_variant_id)->lockForUpdate()->firstOrFail();
            $variant->loadMissing(['product', 'color']);
            $policy = BargainPolicy::fromVariant($variant);
            if (! $policy->bargainEnabled) {
                throw new \InvalidArgumentException('Bargaining is not available for this product right now.');
            }

            if ($acceptedPrice !== null) {
                $explicit = number_format((float) $acceptedPrice, 2, '.', '');
                if (bccomp($explicit, $policy->listPrice, 2) === 1) {
                    $explicit = $policy->listPrice;
                }
                if (! $policy->isAllowedPrice($explicit)) {
                    throw new \InvalidArgumentException('That price isn’t allowed for this product.');
                }
                $chosen = $explicit;
            } else {
                $chosen = $session->current_offer !== null ? (string) $session->current_offer : null;
                if ($chosen === null) {
                    throw new \InvalidArgumentException('There is no offer to accept yet — make an offer first.');
                }
                $chosen = $policy->clampToAllowedRange($chosen);
                if (! $policy->isAllowedPrice($chosen)) {
                    throw new \InvalidArgumentException('That price isn’t allowed for this product.');
                }
            }

            BargainSession::query()
                ->where('customer_key', $session->customer_key)
                ->where('product_variant_id', $session->product_variant_id)
                ->where('id', '!=', $session->id)
                ->where('state', BargainSessionState::Accepted)
                ->whereNull('lock_consumed_at')
                ->update([
                    'lock_consumed_at' => now(),
                    'state' => BargainSessionState::Consumed,
                    'checkout_token' => null,
                ]);

            $session->accepted_price = $chosen;
            $session->checkout_token = Str::random(48);
            $session->state = BargainSessionState::Accepted;
            $session->expires_at = now()->addMinutes((int) config('bargain.lock_ttl_minutes', 60));
            $session->save();

            $base = "Done — I’ve locked PKR {$chosen} for you for checkout. This lock is time‑limited, so when you’re ready, add to bag and checkout with the same phone number you used here.";
            [$context, $hint, $lastCustomerOffer] = $this->buildAiContext($session->id);
            $text = $this->polisher->polishShopkeeperWithContext(
                draftText: $base,
                contextMessages: $context,
                facts: [
                    'list_price' => $policy->listPrice,
                    'current_offer' => $session->current_offer !== null ? (string) $session->current_offer : null,
                    'last_customer_offer' => $lastCustomerOffer,
                    'customer_name' => $session->customer_name ?? null,
                    'product_name' => (string) $variant->product->name,
                    'variant_label' => $this->variantLabel($variant),
                    'color_name' => (string) ($variant->color?->name ?? ''),
                ],
                languageHint: $hint,
            );

            BargainMessage::query()->create([
                'bargain_session_id' => $session->id,
                'role' => 'assistant',
                'body' => $text,
                'meta' => [
                    'kind' => 'accepted',
                    'accepted_price' => $chosen,
                    'checkout_token' => $session->checkout_token,
                    'lock_expires_at' => $session->expires_at?->toIso8601String(),
                ],
            ]);

            return $session->fresh(['messages']);
        });
    }

    public function decline(BargainSession $session, ?User $user, string $customerPhone): BargainSession
    {
        $this->authorize($session, $user, $customerPhone);
        $this->syncExpiry($session);

        if (! in_array($session->state, [BargainSessionState::Open, BargainSessionState::Countered], true)) {
            throw new \InvalidArgumentException('This negotiation is already closed.');
        }

        return DB::transaction(function () use ($session): BargainSession {
            /** @var BargainSession $session */
            $session = BargainSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->syncExpiry($session);
            if (! in_array($session->state, [BargainSessionState::Open, BargainSessionState::Countered], true)) {
                throw new \InvalidArgumentException('This negotiation is already closed.');
            }

            /** @var ProductVariant $variant */
            $variant = ProductVariant::query()->whereKey($session->product_variant_id)->lockForUpdate()->firstOrFail();
            $variant->loadMissing(['product', 'color']);

            $session->state = BargainSessionState::Declined;
            $session->current_offer = null;
            $session->save();

            $base = DeterministicShopkeeperReply::decline();
            [$context, $hint, $lastCustomerOffer] = $this->buildAiContext($session->id);
            $text = $this->polisher->polishShopkeeperWithContext(
                draftText: $base,
                contextMessages: $context,
                facts: [
                    'list_price' => (string) $session->list_price,
                    'current_offer' => null,
                    'last_customer_offer' => $lastCustomerOffer,
                    'customer_name' => $session->customer_name ?? null,
                    'product_name' => (string) $variant->product->name,
                    'variant_label' => $this->variantLabel($variant),
                    'color_name' => (string) ($variant->color?->name ?? ''),
                ],
                languageHint: $hint,
            );

            BargainMessage::query()->create([
                'bargain_session_id' => $session->id,
                'role' => 'assistant',
                'body' => $text,
                'meta' => [
                    'kind' => 'declined',
                ],
            ]);

            return $session->fresh(['messages']);
        });
    }

    public function status(BargainSession $session, ?User $user, string $customerPhone): BargainSession
    {
        $this->authorize($session, $user, $customerPhone);
        $this->syncExpiry($session);

        $session->load([
            'messages' => fn ($q) => $q->orderByDesc('id')->limit(50),
        ]);

        return $session;
    }

    private function authorize(BargainSession $session, ?User $user, string $customerPhone): void
    {
        $normalized = PhoneNormalizer::normalize($customerPhone);
        if ($normalized === null) {
            throw new \InvalidArgumentException('Please provide a valid phone number.');
        }

        if ($session->user_id !== null) {
            if ($user === null || (int) $user->id !== (int) $session->user_id) {
                throw new AuthorizationException('You are not allowed to access this bargaining session.');
            }
        }

        if ((string) $session->customer_phone !== $normalized) {
            throw new AuthorizationException('You are not allowed to access this bargaining session.');
        }
    }

    private function syncExpiry(BargainSession $session): void
    {
        if ($session->expires_at === null) {
            return;
        }

        if ($session->expires_at->isFuture()) {
            return;
        }

        if (in_array($session->state, [BargainSessionState::Open, BargainSessionState::Countered], true)) {
            $session->state = BargainSessionState::Expired;
            $session->save();

            return;
        }

        if ($session->state === BargainSessionState::Accepted && $session->lock_consumed_at === null) {
            $session->state = BargainSessionState::Expired;
            $session->save();
        }
    }

    private function variantLabel(ProductVariant $variant): string
    {
        $variant->loadMissing(['product', 'color']);

        $color = (string) ($variant->color?->name ?? 'Variant');

        return $color.' · '.$variant->sku;
    }

    /**
     * @return array{0: array<int, array{role:string, body:string}>, 1: string, 2: ?string}
     */
    private function buildAiContext(int $sessionId): array
    {
        $maxMessages = (int) config('bargain.ai.context.max_messages', 6);
        $maxCharsPer = (int) config('bargain.ai.context.max_chars_per_message', 300);
        $maxTotal = (int) config('bargain.ai.context.max_total_chars', 1800);

        $messages = BargainMessage::query()
            ->where('bargain_session_id', $sessionId)
            ->orderByDesc('id')
            ->limit(max(1, $maxMessages))
            ->get(['role', 'body', 'meta'])
            ->reverse()
            ->values();

        $context = [];
        $total = 0;
        $lastCustomerBody = null;
        $lastCustomerOffer = null;

        foreach ($messages as $m) {
            $body = preg_replace('/\s+/u', ' ', trim((string) $m->body)) ?? '';
            if ($body === '') {
                continue;
            }
            if (mb_strlen($body) > $maxCharsPer) {
                $body = mb_substr($body, 0, $maxCharsPer);
            }

            $addLen = mb_strlen($body);
            if ($total + $addLen > $maxTotal) {
                break;
            }

            $context[] = ['role' => (string) $m->role, 'body' => $body];
            $total += $addLen;

            if ((string) $m->role === 'customer') {
                $lastCustomerBody = $body;
                $meta = is_array($m->meta) ? $m->meta : [];
                $parsed = $meta['parsed_offer'] ?? null;
                $lastCustomerOffer = is_string($parsed) && $parsed !== '' ? $parsed : $lastCustomerOffer;
            }
        }

        $hint = BargainLanguageHint::fromCustomerText($lastCustomerBody ?? '');

        return [$context, $hint, $lastCustomerOffer];
    }
}
