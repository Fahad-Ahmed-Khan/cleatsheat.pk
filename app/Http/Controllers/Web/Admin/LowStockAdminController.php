<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\VariantSize;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LowStockAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = (string) $request->input('tab', 'low');
        if (! in_array($tab, ['low', 'out'], true)) {
            $tab = 'low';
        }

        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 25);
        if ($perPage <= 0 || $perPage > 100) {
            $perPage = 25;
        }

        $query = VariantSize::query()
            ->with(['variant.product:id,name,slug', 'variant.color:id,name'])
            ->when($search !== '', function ($q) use ($search) {
                $q->whereHas('variant.product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('variant', fn ($vq) => $vq->where('sku', 'like', "%{$search}%"));
            });

        if ($tab === 'out') {
            $query->where('stock_qty', '<=', 0);
        } else {
            $query->where('stock_qty', '>', 0)
                ->whereColumn('stock_qty', '<=', 'low_stock_threshold');
        }

        $rows = $query->orderBy('stock_qty')->paginate($perPage)->withQueryString();

        $rows->through(fn (VariantSize $s): array => [
            'id' => $s->id,
            'product_id' => $s->variant?->product?->id,
            'product_name' => $s->variant?->product?->name ?? '—',
            'sku' => $s->variant?->sku,
            'color' => $s->variant?->color?->name,
            'size_label' => $s->size_label,
            'stock_qty' => $s->stock_qty,
            'low_stock_threshold' => $s->low_stock_threshold,
        ]);

        $stats = [
            'low' => VariantSize::query()
                ->where('stock_qty', '>', 0)
                ->whereColumn('stock_qty', '<=', 'low_stock_threshold')
                ->count(),
            'out' => VariantSize::query()->where('stock_qty', '<=', 0)->count(),
        ];

        return Inertia::render('Admin/Inventory/LowStock', [
            'rows' => $rows,
            'tab' => $tab,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
            'stats' => $stats,
        ]);
    }
}
