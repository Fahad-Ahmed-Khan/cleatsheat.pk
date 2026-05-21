<?php

namespace App\Domain\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethodConfig;
use App\Models\Shipment;
use App\Models\User;
use App\Support\Bargain\PhoneNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class CustomerOrderQueryService
{
    /**
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginateForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->with(['items', 'shipments.courier'])
            ->latest()
            ->paginate($perPage);
    }

    public function countForUser(User $user): int
    {
        return Order::query()->where('user_id', $user->id)->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function toOrderListPayload(Order $order): array
    {
        return [
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'grand_total' => (float) $order->grand_total,
            'placed_at' => $order->created_at?->toIso8601String(),
            'item_count' => $order->relationLoaded('items') ? $order->items->count() : null,
        ];
    }

    public function findOwnedByUser(User $user, string $orderNumber): ?Order
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->where('order_number', $orderNumber)
            ->with([
                'items.variant.product',
                'shipments.courier',
                'shipments.events' => fn ($q) => $q->latest('occurred_at')->latest('id')->limit(30),
                'payments',
            ])
            ->first();
    }

    public function findForPublicTracking(string $orderNumber, string $email): ?Order
    {
        $order = $this->findByOrderNumber($orderNumber);
        if ($order === null || ! $this->orderMatchesEmail($order, $email)) {
            return null;
        }

        return $order;
    }

    /**
     * Resolve a public tracking view from order reference, email, and/or phone.
     *
     * @return array{result: ?array<string, mixed>, choices: list<array<string, mixed>>, error: ?string}
     */
    public function lookupForPublicTracking(?string $orderNumber, ?string $email, ?string $phone): array
    {
        $orderNumber = trim((string) $orderNumber);
        $email = mb_strtolower(trim((string) $email));
        $phone = trim((string) $phone);

        if ($orderNumber !== '') {
            $order = $this->findByOrderNumber($orderNumber);
            if ($order === null) {
                return ['result' => null, 'choices' => [], 'error' => 'No order found with that reference.'];
            }
            if ($email !== '' && ! $this->orderMatchesEmail($order, $email)) {
                return ['result' => null, 'choices' => [], 'error' => 'That email does not match this order.'];
            }
            if ($phone !== '' && ! $this->orderMatchesPhone($order, $phone)) {
                return ['result' => null, 'choices' => [], 'error' => 'That phone number does not match this order.'];
            }

            return ['result' => $this->toTrackingPayload($order), 'choices' => [], 'error' => null];
        }

        $orders = $this->findOrdersByContact($email, $phone);

        if ($orders->isEmpty()) {
            return ['result' => null, 'choices' => [], 'error' => 'No orders found for those details.'];
        }

        if ($orders->count() === 1) {
            return ['result' => $this->toTrackingPayload($orders->first()), 'choices' => [], 'error' => null];
        }

        return [
            'result' => null,
            'choices' => $orders->map(fn (Order $o) => $this->toTrackingChoicePayload($o))->values()->all(),
            'error' => null,
        ];
    }

    public function findByOrderNumber(string $orderNumber): ?Order
    {
        return Order::query()
            ->where('order_number', trim($orderNumber))
            ->with($this->trackingRelations())
            ->first();
    }

    public function orderMatchesEmail(Order $order, string $email): bool
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return false;
        }

        if ($order->guest_email !== null && mb_strtolower($order->guest_email) === $email) {
            return true;
        }

        return $order->relationLoaded('user')
            ? $order->user?->email !== null && mb_strtolower($order->user->email) === $email
            : $order->user()->where('email', $email)->exists();
    }

    public function orderMatchesPhone(Order $order, string $phone): bool
    {
        $snap = $order->shipping_address_snapshot;
        if (! is_array($snap)) {
            return false;
        }

        $stored = $snap['phone'] ?? null;
        if (! is_string($stored) || trim($stored) === '') {
            return false;
        }

        $normalizedStored = PhoneNormalizer::normalize($stored);
        $normalizedInput = PhoneNormalizer::normalize($phone);
        if ($normalizedStored !== null && $normalizedInput !== null && $normalizedStored === $normalizedInput) {
            return true;
        }

        $digitsStored = preg_replace('/\D+/', '', $stored) ?? '';
        $digitsInput = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digitsStored) < 10 || strlen($digitsInput) < 10) {
            return false;
        }

        return substr($digitsStored, -10) === substr($digitsInput, -10);
    }

    /**
     * @return Collection<int, Order>
     */
    private function findOrdersByContact(string $email, string $phone): Collection
    {
        if ($email !== '') {
            $orders = Order::query()
                ->where(function ($q) use ($email): void {
                    $q->where('guest_email', $email)
                        ->orWhereHas('user', fn ($uq) => $uq->where('email', $email));
                })
                ->with($this->trackingRelations())
                ->latest()
                ->limit(50)
                ->get();

            if ($phone !== '') {
                return $orders
                    ->filter(fn (Order $o) => $this->orderMatchesPhone($o, $phone))
                    ->values();
            }

            return $orders;
        }

        return $this->findAllByPhone($phone);
    }

    /**
     * @return Collection<int, Order>
     */
    private function findAllByPhone(string $phone): Collection
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 10) {
            return new Collection;
        }

        $suffix = substr($digits, -10);

        return Order::query()
            ->where('shipping_address_snapshot->phone', 'like', '%'.$suffix.'%')
            ->with($this->trackingRelations())
            ->latest()
            ->limit(50)
            ->get()
            ->filter(fn (Order $o) => $this->orderMatchesPhone($o, $phone))
            ->values();
    }

    /**
     * @return array<int, string|\Closure>
     */
    private function trackingRelations(): array
    {
        return [
            'user',
            'items.variant.product.images',
            'payments',
            'shipments.courier',
            'shipments.events' => fn ($q) => $q->latest('occurred_at')->latest('id')->limit(20),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toTrackingChoicePayload(Order $order): array
    {
        return [
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'grand_total' => (float) $order->grand_total,
            'placed_at' => $order->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toTrackingPayload(Order $order): array
    {
        return [
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'placed_at' => $order->created_at?->toIso8601String(),
            'payment' => [
                'gateway' => $order->payment_gateway,
                'gateway_label' => $this->gatewayLabel($order->payment_gateway),
                'status' => $order->payment_status->value,
            ],
            'totals' => [
                'subtotal' => (float) $order->subtotal,
                'discount_total' => (float) $order->discount_total,
                'shipping_total' => (float) $order->shipping_total,
                'cod_fee' => (float) $order->cod_fee,
                'grand_total' => (float) $order->grand_total,
            ],
            'items' => $order->items->map(fn ($i) => $this->serializeOrderItem($i))->values()->all(),
            'payments' => $order->payments
                ->sortByDesc('id')
                ->map(fn ($p) => $this->serializePayment($p))
                ->values()
                ->all(),
            'shipments' => $order->shipments->map(fn ($s) => $this->serializeShipment($s))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toOrderDetailPayload(Order $order): array
    {
        return [
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'placed_at' => $order->created_at?->toIso8601String(),
            'grand_total' => (float) $order->grand_total,
            'payment_gateway' => $order->payment_gateway,
            'payment' => [
                'gateway' => $order->payment_gateway,
                'gateway_label' => $this->gatewayLabel($order->payment_gateway),
                'status' => $order->payment_status->value,
            ],
            'totals' => [
                'subtotal' => (float) $order->subtotal,
                'discount_total' => (float) $order->discount_total,
                'shipping_total' => (float) $order->shipping_total,
                'cod_fee' => (float) $order->cod_fee,
                'grand_total' => (float) $order->grand_total,
            ],
            'shipping_address_snapshot' => $order->shipping_address_snapshot,
            'items' => $order->items->map(fn ($i) => $this->serializeOrderItem($i))->values()->all(),
            'payments' => $order->relationLoaded('payments')
                ? $order->payments
                    ->sortByDesc('id')
                    ->map(fn ($p) => $this->serializePayment($p))
                    ->values()
                    ->all()
                : [],
            'shipments' => $order->shipments->map(fn ($s) => $this->serializeShipment($s))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrderItem(OrderItem $i): array
    {
        $imagePath = null;
        if ($i->relationLoaded('variant') && $i->variant?->relationLoaded('product')) {
            $imagePath = $i->variant->product->images
                ->sortBy('sort_order')
                ->first()
                ?->path;
        }

        return [
            'id' => $i->id,
            'product_name' => $i->product_name,
            'variant_label' => $i->variant_label,
            'sku' => $i->sku,
            'size_label' => $i->size_label,
            'quantity' => $i->quantity,
            'unit_price' => (float) $i->unit_price,
            'line_total' => (float) $i->line_total,
            'image_url' => $this->absoluteAssetUrl($imagePath),
        ];
    }

    private function absoluteAssetUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return rtrim(config('app.url'), '/').'/'.ltrim($url, '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePayment(Payment $p): array
    {
        return [
            'gateway' => $p->gateway,
            'gateway_label' => $this->gatewayLabel($p->gateway),
            'status' => $p->status->value,
            'amount' => (float) $p->amount,
            'paid_at' => $p->paid_at?->toIso8601String(),
        ];
    }

    private function gatewayLabel(?string $code): string
    {
        if ($code === null || $code === '') {
            return '—';
        }

        $label = PaymentMethodConfig::query()
            ->where('gateway_code', $code)
            ->value('customer_label');

        if (is_string($label) && $label !== '') {
            return $label;
        }

        return ucwords(str_replace('_', ' ', $code));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeShipment(Shipment $s): array
    {
        return [
            'id' => $s->id,
            'courier' => $s->courier?->name,
            'tracking_number' => $s->tracking_number,
            'booking_reference' => $s->booking_reference,
            'status' => $s->status->value,
            'label_url' => $s->label_url,
            'events' => $s->events->take(30)->map(fn ($e) => [
                'status' => $e->status,
                'description' => $e->description,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
