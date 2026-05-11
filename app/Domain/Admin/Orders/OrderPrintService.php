<?php

namespace App\Domain\Admin\Orders;

use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\ShippingSetting;
use Barryvdh\Snappy\Facades\SnappyPdf;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;

class OrderPrintService
{
    /**
     * @param  array<int>  $orderIds
     */
    public function labelsPdf(array $orderIds, ?string $paperSize, string $layout = 'one_per_page'): HttpResponse
    {
        $orders = $this->loadOrdersForPrint($orderIds);
        $settings = ShippingSetting::current();

        $html = view('admin.orders.print.labels', [
            'orders' => $orders,
            'paperSize' => $paperSize,
            'layout' => $layout,
            'sender' => is_array($settings->sender_snapshot) ? $settings->sender_snapshot : [],
        ])->render();

        $paper = match ($layout) {
            'three_per_a4' => 'A4',
            default => ($paperSize === 'a5' ? 'A5' : 'A6'),
        };

        $pdf = SnappyPdf::loadHTML($html)
            ->setPaper($paper)
            ->setOption('margin-top', '2mm')
            ->setOption('margin-right', '2mm')
            ->setOption('margin-bottom', '2mm')
            ->setOption('margin-left', '2mm')
            ->setOption('disable-smart-shrinking', true);

        return response($pdf->inline(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="shipping-labels.pdf"',
        ]);
    }

    /**
     * @param  array<int>  $orderIds
     */
    public function packingSlipsPdf(array $orderIds): HttpResponse
    {
        $orders = $this->loadOrdersForPrint($orderIds);
        $settings = ShippingSetting::current();

        $html = view('admin.orders.print.packing-slips', [
            'orders' => $orders,
            'sender' => is_array($settings->sender_snapshot) ? $settings->sender_snapshot : [],
        ])->render();

        $pdf = SnappyPdf::loadHTML($html)->setPaper('A4');

        return response($pdf->inline(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="packing-slips.pdf"',
        ]);
    }

    /**
     * @param  array<int>  $orderIds
     * @return Collection<int, array<string, mixed>>
     */
    private function loadOrdersForPrint(array $orderIds)
    {
        $orders = Order::query()
            ->whereIn('id', $orderIds)
            ->with(['items', 'shipments.courier', 'adjustments'])
            ->get();

        return $orders->map(function (Order $order) {
            $ship = is_array($order->shipping_address_snapshot) ? $order->shipping_address_snapshot : [];
            $bill = is_array($order->billing_address_snapshot) ? $order->billing_address_snapshot : [];

            $shipment = $order->shipments
                ->sortByDesc('id')
                ->first();

            $tracking = $shipment?->tracking_number;
            $codAmount = $shipment?->cod_amount;

            $barcodeText = (string) ($tracking ?: $order->order_number);
            $adminUrl = route('admin.orders.show', $order->id);

            $gateway = strtolower((string) ($order->payment_gateway ?? ''));
            $isCod = $gateway === 'cod';
            $codForLabel = $isCod
                ? (string) ($codAmount ?? $order->grand_total)
                : '0';

            /** @var OrderAdjustment|null $activeAdj */
            $activeAdj = $order->adjustments
                ->whereNull('voided_at')
                ->sortByDesc('id')
                ->first();

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'created_at' => $order->created_at?->format('M j, Y H:i'),
                'customer_name' => (string) ($ship['full_name'] ?? ''),
                'customer_phone' => (string) ($ship['phone'] ?? ''),
                'shipping_address' => $ship,
                'billing_address' => $bill,
                'items' => $order->items,
                'payment_gateway' => $order->payment_gateway,
                'is_cod' => $isCod,
                'cod_label_amount' => $codForLabel,
                'subtotal' => (string) $order->subtotal,
                'discount_total' => (string) $order->discount_total,
                'grand_total' => (string) $order->grand_total,
                'admin_discount' => $activeAdj ? [
                    'type' => $activeAdj->type,
                    'value' => (string) $activeAdj->value,
                    'reason' => $activeAdj->reason,
                ] : null,
                'shipment' => $shipment ? [
                    'id' => $shipment->id,
                    'status' => $shipment->status->value,
                    'tracking_number' => $tracking,
                    'courier_name' => $shipment->courier?->name,
                    'cod_amount' => $codAmount !== null ? (string) $codAmount : null,
                    'is_booked' => in_array($shipment->status, [ShipmentStatus::Booked, ShipmentStatus::InTransit, ShipmentStatus::Delivered], true),
                ] : null,
                'barcode_text' => $barcodeText,
                'qr_url' => $adminUrl,
            ];
        })->values();
    }
}
