<?php

namespace App\Domain\Orders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
        $email = mb_strtolower(trim($email));

        return Order::query()
            ->where('order_number', $orderNumber)
            ->where(function ($q) use ($email): void {
                $q->where('guest_email', $email)
                    ->orWhereHas('user', fn ($uq) => $uq->where('email', $email));
            })
            ->with([
                'shipments.courier',
                'shipments.events' => fn ($q) => $q->latest('occurred_at')->latest('id')->limit(20),
            ])
            ->first();
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
            'grand_total' => (float) $order->grand_total,
            'payment_gateway' => $order->payment_gateway,
            'shipping_address_snapshot' => $order->shipping_address_snapshot,
            'items' => $order->items->map(fn ($i) => [
                'id' => $i->id,
                'product_name' => $i->product_name,
                'variant_label' => $i->variant_label,
                'sku' => $i->sku,
                'size_label' => $i->size_label,
                'quantity' => $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'line_total' => (float) $i->line_total,
            ])->values()->all(),
            'shipments' => $order->shipments->map(fn ($s) => $this->serializeShipment($s))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeShipment(\App\Models\Shipment $s): array
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
