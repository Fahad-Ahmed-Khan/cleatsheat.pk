<?php

namespace App\Domain\Shipping;

use App\Enums\CourierAssignmentMode;
use App\Enums\ShipmentStatus;
use App\Models\Courier;
use App\Models\CourierAccount;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingSetting;

class CourierDispatchService
{
    public function createPendingShipment(Order $order): Shipment
    {
        $settings = ShippingSetting::current();

        $courier = $this->resolveCourier($order, $settings);
        $account = $this->resolveAccount($courier, $order);

        $receiver = $this->receiverSnapshot($order);
        $sender = is_array($settings->sender_snapshot) ? $settings->sender_snapshot : [];

        return Shipment::query()->create([
            'order_id' => $order->id,
            'courier_id' => $courier?->id,
            'courier_account_id' => $account?->id,
            'tracking_number' => null,
            'booking_reference' => null,
            'status' => ShipmentStatus::Pending,
            'cod_amount' => $this->codAmount($order),
            'sender_snapshot' => $sender,
            'receiver_snapshot' => $receiver,
            'weight_kg' => $settings->default_weight_kg,
            'length_cm' => $settings->default_length_cm,
            'width_cm' => $settings->default_width_cm,
            'height_cm' => $settings->default_height_cm,
            'meta' => [
                'note' => $courier === null
                    ? 'Assign courier (manual mode or no default) then book from admin.'
                    : 'Ready to book when payment rules allow.',
            ],
        ]);
    }

    /**
     * Ensure the order has a pending shipment assigned to the given courier, ready
     * for the admin to book. Reuses the latest pending shipment if one exists, and
     * re-resolves the matching active account whenever the courier changes.
     */
    public function ensurePendingShipmentWithCourier(Order $order, Courier $courier): Shipment
    {
        $shipment = $order->shipments()
            ->where('status', ShipmentStatus::Pending)
            ->latest('id')
            ->first();

        if ($shipment === null) {
            $shipment = $this->createPendingShipment($order);
        }

        if ($shipment->courier_id !== $courier->id || $shipment->courier_account_id === null) {
            $account = $this->resolveAccount($courier, $order);
            $shipment->courier_id = $courier->id;
            $shipment->courier_account_id = $account?->id;
            $shipment->save();
        }

        return $shipment;
    }

    /**
     * Resolve the courier most likely to be used for booking — preferred if set,
     * otherwise the configured system default, else first active. Used to pre-fill
     * the admin "Book with courier" picker.
     */
    public function resolveDefaultCourierId(Order $order): ?int
    {
        $settings = ShippingSetting::current();
        $courier = $this->resolveCourier($order, $settings);

        return $courier?->id;
    }

    private function resolveCourier(Order $order, ShippingSetting $settings): ?Courier
    {
        if ($order->courier_assignment === CourierAssignmentMode::Manual) {
            if ($order->preferred_courier_id) {
                return Courier::query()->active()->whereKey($order->preferred_courier_id)->first();
            }

            return null;
        }

        if ($order->preferred_courier_id) {
            return Courier::query()->active()->whereKey($order->preferred_courier_id)->first();
        }

        if ($settings->default_courier_id) {
            return Courier::query()->active()->whereKey($settings->default_courier_id)->first();
        }

        return Courier::query()->active()->orderBy('sort_order')->orderBy('id')->first();
    }

    private function resolveAccount(?Courier $courier, Order $order): ?CourierAccount
    {
        if ($courier === null) {
            return null;
        }

        $query = CourierAccount::query()
            ->where('courier_id', $courier->id)
            ->where('is_active', true);

        if ($order->payment_gateway === 'cod') {
            $query->where('cod_allowed', true);
        }

        $default = (clone $query)->where('is_default', true)->first();

        return $default ?? $query->orderBy('id')->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function receiverSnapshot(Order $order): array
    {
        $a = $order->shipping_address_snapshot;

        return [
            'full_name' => $a['full_name'] ?? '',
            'phone' => $a['phone'] ?? '',
            'line1' => $a['line1'] ?? '',
            'city' => $a['city'] ?? '',
            'area' => $a['area'] ?? null,
            'postal_code' => $a['postal_code'] ?? null,
        ];
    }

    private function codAmount(Order $order): ?string
    {
        if ($order->payment_gateway === 'cod') {
            return (string) $order->grand_total;
        }

        return null;
    }
}
