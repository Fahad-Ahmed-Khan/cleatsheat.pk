<?php

namespace App\Domain\Admin\Orders;

use App\Models\Order;
use App\Models\OrderAdjustment;

class OrderPricingService
{
    public function recalcDiscountAndGrandTotal(Order $order): void
    {
        $order->loadMissing('adjustments');

        $baseSubtotal = (string) $order->subtotal;
        $shipping = (string) $order->shipping_total;
        $codFee = (string) $order->cod_fee;

        $adminDiscount = $this->adminDiscountAmount($order);

        // discount_total in this codebase already represents total discount bucket.
        // We'll store admin discount there for now (no coupons implemented yet).
        $order->discount_total = $adminDiscount;

        $netBeforeFees = bcadd(bcsub($baseSubtotal, $adminDiscount, 2), $shipping, 2);
        $order->grand_total = bcadd($netBeforeFees, $codFee, 2);
    }

    public function adminDiscountAmount(Order $order): string
    {
        /** @var \Illuminate\Support\Collection<int, OrderAdjustment> $rows */
        $rows = $order->adjustments
            ->filter(fn (OrderAdjustment $a) => $a->voided_at === null);

        // v1: only one active admin discount is expected; if multiple, sum them.
        $total = '0';
        foreach ($rows as $a) {
            $amt = '0';
            if ($a->type === 'fixed') {
                $amt = (string) $a->value;
            } elseif ($a->type === 'percent') {
                $pct = (string) $a->value; // e.g. 10 means 10%
                $amt = bcdiv(bcmul((string) $order->subtotal, $pct, 6), '100', 2);
            }

            if (bccomp($amt, '0', 2) === 1) {
                $total = bcadd($total, $amt, 2);
            }
        }

        // Clamp to subtotal
        if (bccomp($total, (string) $order->subtotal, 2) === 1) {
            return (string) $order->subtotal;
        }

        return $total;
    }
}

