<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Admin\Orders\OrderAuditLogger;
use App\Domain\Admin\Orders\OrderPricingService;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderAdjustment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderAdjustmentsAdminController extends Controller
{
    public function __construct(
        private readonly OrderPricingService $pricing,
        private readonly OrderAuditLogger $audit,
    ) {}

    public function setAdminDiscount(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('update', $order);

        $lockAfterDelivered = (bool) config('store.admin_discount_lock_after_delivered', true);
        if ($lockAfterDelivered && $order->status === OrderStatus::Delivered) {
            return back()->with('error', 'Admin discount cannot be changed after delivery.');
        }

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(['fixed', 'percent'])],
            'value' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        // Void existing active adjustments (admin discount is singular in UI).
        $order->load('adjustments');
        foreach ($order->adjustments as $adj) {
            if ($adj->voided_at === null) {
                $adj->voided_at = now();
                $adj->save();
            }
        }

        $adj = OrderAdjustment::query()->create([
            'order_id' => $order->id,
            'type' => $data['type'],
            'value' => $data['value'],
            'reason' => $data['reason'] ?? null,
            'created_by' => $request->user()?->id,
            'voided_at' => null,
        ]);

        $fresh = $order->fresh(['adjustments']);
        $this->pricing->recalcDiscountAndGrandTotal($fresh);
        $fresh->save();

        $this->audit->log(
            $fresh,
            'admin_discount_set',
            $request->user(),
            [
                'adjustment_id' => $adj->id,
                'type' => $adj->type,
                'value' => (string) $adj->value,
                'reason' => $adj->reason,
            ],
        );

        return back()->with('status', 'Admin discount updated.');
    }
}

