<?php

namespace App\Domain\Finance;

use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Collection;

class CourierSettlementService
{
    /**
     * Per-courier COD settlement view.
     *
     * Methodology:
     *   - "Delivered COD" shipments = shipments with status=delivered AND order.payment_gateway looks COD.
     *   - "Outstanding" = delivered COD shipments where the order's payment_status is NOT yet paid
     *     (courier is still holding the cash on our behalf).
     *   - "Settled" = delivered COD shipments where the order's payment_status is paid.
     *   - "Discrepancies" = shipments whose cod_amount differs from the order grand_total (data quality flag).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function summaryPerCourier(): Collection
    {
        $couriers = Courier::query()->orderBy('name')->get(['id', 'name', 'code', 'adapter']);

        return $couriers->map(function (Courier $courier): array {
            $deliveredCodQuery = $this->deliveredCodShipmentsQuery($courier->id);

            $outstanding = (clone $deliveredCodQuery)
                ->whereHas('order', fn ($q) => $q->where('payment_status', '!=', PaymentStatus::Paid));

            $settled = (clone $deliveredCodQuery)
                ->whereHas('order', fn ($q) => $q->where('payment_status', PaymentStatus::Paid));

            return [
                'courier_id' => $courier->id,
                'courier_name' => $courier->name,
                'courier_code' => $courier->code,
                'adapter' => $courier->adapter,
                'delivered_count' => (clone $deliveredCodQuery)->count(),
                'outstanding_count' => (clone $outstanding)->count(),
                'outstanding_amount' => (float) (clone $outstanding)->sum('cod_amount'),
                'settled_count' => (clone $settled)->count(),
                'settled_amount' => (float) (clone $settled)->sum('cod_amount'),
                'discrepancy_count' => $this->discrepancyQuery($courier->id)->count(),
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function outstandingShipmentsForCourier(int $courierId, int $limit = 100): Collection
    {
        return $this->deliveredCodShipmentsQuery($courierId)
            ->whereHas('order', fn ($q) => $q->where('payment_status', '!=', PaymentStatus::Paid))
            ->with('order:id,order_number,grand_total,payment_status,payment_gateway,user_id,guest_email')
            ->orderBy('delivered_at')
            ->limit($limit)
            ->get()
            ->map(fn (Shipment $s): array => [
                'shipment_id' => $s->id,
                'tracking_number' => $s->tracking_number,
                'cod_amount' => (float) $s->cod_amount,
                'delivered_at' => $s->delivered_at?->toIso8601String(),
                'order_id' => $s->order_id,
                'order_number' => $s->order?->order_number,
                'order_total' => (float) ($s->order?->grand_total ?? 0),
                'order_payment_status' => $s->order?->payment_status?->value,
                'customer' => $s->order?->guest_email ?? '—',
            ]);
    }

    private function deliveredCodShipmentsQuery(int $courierId)
    {
        return Shipment::query()
            ->where('courier_id', $courierId)
            ->where('status', ShipmentStatus::Delivered)
            ->whereHas('order', function ($q) {
                $q->where(function ($qq) {
                    $qq->where('payment_gateway', 'like', '%cod%')
                        ->orWhere('payment_gateway', 'like', '%cash%');
                });
            });
    }

    private function discrepancyQuery(int $courierId)
    {
        return Shipment::query()
            ->where('courier_id', $courierId)
            ->where('status', ShipmentStatus::Delivered)
            ->whereNotNull('cod_amount')
            ->whereHas('order', function ($q) {
                $q->whereRaw('shipments.cod_amount != orders.grand_total');
            });
    }
}
