<?php

namespace App\Domain\Checkout;

use App\Domain\Bargain\PriceLockResolver;
use App\Enums\BargainSessionState;
use App\Models\BargainSession;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantSize;
use App\Support\Bargain\PhoneNormalizer;
use App\Support\Storage\PublicAssetUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CartService
{
    public function __construct(
        private readonly PriceLockResolver $priceLocks,
    ) {}

    /**
     * Stateless cart for mobile / API clients (no session).
     *
     * @throws InvalidArgumentException
     */
    public function getOrCreateCartForApi(?User $user, ?string $guestCartToken): Cart
    {
        if ($user !== null) {
            return Cart::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['currency' => 'PKR']
            );
        }

        if ($guestCartToken === null || trim($guestCartToken) === '') {
            throw new InvalidArgumentException('Send header X-Guest-Cart-Token (UUID) for guest carts, or authenticate with Bearer token.');
        }

        $guestCartToken = trim($guestCartToken);
        if (! Str::isUuid($guestCartToken)) {
            throw new InvalidArgumentException('X-Guest-Cart-Token must be a valid UUID.');
        }

        return Cart::query()->firstOrCreate(
            ['guest_token' => $guestCartToken, 'user_id' => null],
            ['currency' => 'PKR']
        );
    }

    public function getOrCreateCart(?User $user, Request $request): Cart
    {
        if ($user !== null) {
            return Cart::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['currency' => 'PKR']
            );
        }

        $token = $request->session()->get('guest_cart_token');
        if ($token === null) {
            $token = (string) Str::uuid();
            $request->session()->put('guest_cart_token', $token);
        }

        return Cart::query()->firstOrCreate(
            ['guest_token' => $token, 'user_id' => null],
            ['currency' => 'PKR']
        );
    }

    public function mergeGuestCartIntoUser(User $user, Request $request): void
    {
        $token = $request->session()->pull('guest_cart_token');
        if ($token === null) {
            return;
        }

        $guestCart = Cart::query()->where('guest_token', $token)->whereNull('user_id')->first();
        if ($guestCart === null || $guestCart->items()->doesntExist()) {
            return;
        }

        $userCart = Cart::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['currency' => 'PKR']
        );

        foreach ($guestCart->items as $item) {
            $existing = CartItem::query()
                ->where('cart_id', $userCart->id)
                ->where('product_variant_id', $item->product_variant_id)
                ->where('size_label', $item->size_label)
                ->where('pricing_key', $item->pricing_key ?? 'regular')
                ->first();

            if ($existing) {
                $existing->quantity += $item->quantity;
                $existing->save();
            } else {
                $item->cart_id = $userCart->id;
                $item->save();
            }
        }

        $guestCart->items()->delete();
        $guestCart->delete();
    }

    public function addLine(Cart $cart, int $variantId, string $sizeLabel, int $qty, ?User $user = null, ?string $bargainPhone = null): CartItem
    {
        if ($qty < 1) {
            throw new InvalidArgumentException('Invalid quantity');
        }

        $variant = ProductVariant::query()->with('sizes')->findOrFail($variantId);
        /** @var VariantSize|null $size */
        $size = $variant->sizes->firstWhere('size_label', $sizeLabel);
        if ($size === null || $size->stock_qty < $qty) {
            throw new InvalidArgumentException('Insufficient stock for selected size');
        }

        $price = (string) $variant->price;
        $pricingKey = 'regular';

        $normalizedBargainPhone = PhoneNormalizer::normalize($bargainPhone);
        $lock = $this->priceLocks->lockForCart($variant->id, $user, $normalizedBargainPhone);
        if ($lock !== null && $lock->accepted_price !== null) {
            $locked = (string) $lock->accepted_price;
            if (bccomp($locked, $price, 2) !== 1) {
                $price = $locked;
                $pricingKey = 'bargain:'.$lock->id;
            }
        }

        $line = CartItem::query()->firstOrNew([
            'cart_id' => $cart->id,
            'product_variant_id' => $variantId,
            'size_label' => $sizeLabel,
            'pricing_key' => $pricingKey,
        ]);

        $newQty = ($line->exists ? $line->quantity : 0) + $qty;
        if ($size->stock_qty < $newQty) {
            throw new InvalidArgumentException('Insufficient stock for selected size');
        }

        $line->quantity = $newQty;
        $line->unit_price_snapshot = $price;
        $line->pricing_key = $pricingKey;
        $line->save();

        return $line;
    }

    /**
     * When a bargain lock expires (or is no longer valid), cart lines tagged with that lock should show list price again.
     */
    public function revertStaleBargainLines(Cart $cart): void
    {
        $cart = $cart->fresh(['items']);
        if ($cart === null || $cart->items->isEmpty()) {
            return;
        }

        $bargainItemIds = $cart->items
            ->filter(fn (CartItem $item) => str_starts_with((string) ($item->pricing_key ?? ''), 'bargain:'))
            ->pluck('id')
            ->all();

        if ($bargainItemIds === []) {
            return;
        }

        DB::transaction(function () use ($bargainItemIds): void {
            foreach ($bargainItemIds as $id) {
                /** @var CartItem|null $item */
                $item = CartItem::query()->whereKey($id)->lockForUpdate()->first();
                if ($item === null) {
                    continue;
                }
                if (! str_starts_with((string) ($item->pricing_key ?? ''), 'bargain:')) {
                    continue;
                }
                $sessionId = (int) substr((string) $item->pricing_key, strlen('bargain:'));
                if ($sessionId < 1) {
                    continue;
                }
                $session = BargainSession::query()->find($sessionId);
                if ($this->bargainLineStillBackedByActiveLock($item, $session)) {
                    continue;
                }
                $this->revertCartLineToRegularList($item);
            }
        });
    }

    private function bargainLineStillBackedByActiveLock(CartItem $item, ?BargainSession $session): bool
    {
        if ($session === null) {
            return false;
        }
        if ((int) $session->product_variant_id !== (int) $item->product_variant_id) {
            return false;
        }
        if ($session->state !== BargainSessionState::Accepted) {
            return false;
        }
        if ($session->accepted_price === null) {
            return false;
        }
        if ($session->lock_consumed_at !== null) {
            return false;
        }
        if ($session->expires_at === null || $session->expires_at <= now()) {
            return false;
        }

        return bccomp((string) $session->accepted_price, (string) $item->unit_price_snapshot, 2) === 0;
    }

    private function revertCartLineToRegularList(CartItem $item): void
    {
        $variant = ProductVariant::query()->find($item->product_variant_id);
        if ($variant === null) {
            return;
        }
        $list = (string) $variant->price;
        $regular = CartItem::query()
            ->where('cart_id', $item->cart_id)
            ->where('product_variant_id', $item->product_variant_id)
            ->where('size_label', $item->size_label)
            ->where('pricing_key', 'regular')
            ->where('id', '!=', $item->id)
            ->lockForUpdate()
            ->first();
        if ($regular !== null) {
            $regular->quantity += $item->quantity;
            $regular->save();
            $item->delete();

            return;
        }

        $item->pricing_key = 'regular';
        $item->unit_price_snapshot = $list;
        $item->save();
    }

    public function updateQuantity(CartItem $item, int $qty): void
    {
        if ($qty < 1) {
            $item->delete();

            return;
        }

        $variant = $item->variant()->with('sizes')->firstOrFail();
        $size = $variant->sizes->firstWhere('size_label', $item->size_label);
        if ($size === null || $size->stock_qty < $qty) {
            throw new InvalidArgumentException('Insufficient stock');
        }

        $item->quantity = $qty;
        $item->save();
    }

    /**
     * Normalized cart payload for JSON APIs (Flutter / RN).
     *
     * @return array{guest_cart_token: string|null, lines: list<array<string, mixed>>, subtotal: float}
     */
    public function buildCartPayload(Cart $cart): array
    {
        $this->revertStaleBargainLines($cart);
        $cart->load(['items.variant.product.images', 'items.variant.color']);

        $lines = $cart->items->map(function (CartItem $item) {
            $v = $item->variant;
            $img = $v->product->images->first();

            return [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'size_label' => $item->size_label,
                'unit_price' => (float) $item->unit_price_snapshot,
                'line_total' => round((float) $item->unit_price_snapshot * $item->quantity, 2),
                'variant' => [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'color' => $v->color->name,
                ],
                'product' => [
                    'id' => $v->product->id,
                    'name' => $v->product->name,
                    'slug' => $v->product->slug,
                    'image' => PublicAssetUrl::resolve($img?->path),
                ],
            ];
        })->values()->all();

        $subtotal = (float) collect($lines)->sum('line_total');

        return [
            'guest_cart_token' => $cart->user_id === null ? $cart->guest_token : null,
            'lines' => $lines,
            'subtotal' => $subtotal,
        ];
    }
}
