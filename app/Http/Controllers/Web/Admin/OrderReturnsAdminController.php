<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Admin\Orders\OrderAuditLogger;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\VariantSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderReturnsAdminController extends Controller
{
    public function __construct(
        private readonly OrderAuditLogger $audit,
    ) {}

    public function store(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('update', $order);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'restock' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        $restock = (bool) ($data['restock'] ?? false);

        $order->load('items');
        $itemsById = $order->items->keyBy('id');

        foreach ($data['items'] as $row) {
            $itemId = (int) $row['order_item_id'];
            $qty = (int) $row['qty'];
            /** @var OrderItem|null $item */
            $item = $itemsById->get($itemId);
            if ($item === null) {
                return back()->with('error', 'Invalid order item selected.');
            }
            if ($qty > (int) $item->quantity) {
                return back()->with('error', 'Return qty cannot exceed purchased qty.');
            }
        }

        $created = DB::transaction(function () use ($order, $data, $restock, $request, $itemsById) {
            $ret = OrderReturn::query()->create([
                'order_id' => $order->id,
                'reason' => $data['reason'],
                'restock' => $restock,
                'created_by' => $request->user()?->id,
                'meta' => null,
            ]);

            $createdItems = [];

            foreach ($data['items'] as $row) {
                $itemId = (int) $row['order_item_id'];
                $qty = (int) $row['qty'];
                /** @var OrderItem $item */
                $item = $itemsById->get($itemId);

                OrderReturnItem::query()->create([
                    'order_return_id' => $ret->id,
                    'order_item_id' => $itemId,
                    'qty' => $qty,
                ]);

                if ($restock && $item->product_variant_id) {
                    $size = VariantSize::query()
                        ->where('product_variant_id', $item->product_variant_id)
                        ->where('size_label', $item->size_label)
                        ->lockForUpdate()
                        ->first();
                    if ($size !== null) {
                        $size->stock_qty += $qty;
                        $size->save();
                    }
                }

                $createdItems[] = [
                    'order_item_id' => $itemId,
                    'qty' => $qty,
                    'sku' => $item->sku,
                    'size_label' => $item->size_label,
                ];
            }

            return [$ret, $createdItems];
        });

        /** @var OrderReturn $ret */
        [$ret, $createdItems] = $created;

        $this->audit->log(
            $order,
            'return_created',
            $request->user(),
            [
                'return_id' => $ret->id,
                'reason' => $ret->reason,
                'restock' => $ret->restock,
                'items' => $createdItems,
            ],
        );

        return back()->with('status', 'Return created.');
    }
}

