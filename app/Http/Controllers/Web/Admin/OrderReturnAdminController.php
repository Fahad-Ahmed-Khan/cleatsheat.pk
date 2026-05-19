<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class OrderReturnAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $restock = $request->input('restock');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $perPage = (int) $request->input('per_page', 25);
        if ($perPage <= 0 || $perPage > 100) {
            $perPage = 25;
        }

        $returns = OrderReturn::query()
            ->with(['order:id,order_number,guest_email,user_id,grand_total', 'order.user:id,email,name', 'items'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->whereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$search}%"))
                        ->orWhere('reason', 'like', "%{$search}%");
                });
            })
            ->when($restock !== null && $restock !== '', function ($q) use ($restock) {
                $q->where('restock', $restock === '1' || $restock === 1 || $restock === true);
            })
            ->when($dateFrom, function ($q, $v) {
                try {
                    $q->where('created_at', '>=', Carbon::parse($v)->startOfDay());
                } catch (\Throwable) {
                }
            })
            ->when($dateTo, function ($q, $v) {
                try {
                    $q->where('created_at', '<=', Carbon::parse($v)->endOfDay());
                } catch (\Throwable) {
                }
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $returns->through(fn (OrderReturn $r): array => [
            'id' => $r->id,
            'order_id' => $r->order_id,
            'order_number' => $r->order?->order_number,
            'customer_email' => $r->order?->user?->email ?? $r->order?->guest_email,
            'reason' => $r->reason,
            'restock' => (bool) $r->restock,
            'created_at' => $r->created_at?->toIso8601String(),
            'created_at_human' => $r->created_at?->format('M j, Y H:i'),
            'units' => (int) $r->items->sum('qty'),
            'lines' => $r->items->count(),
        ]);

        $stats = [
            'total' => OrderReturn::query()->count(),
            'last_30d' => OrderReturn::query()->where('created_at', '>=', now()->subDays(30))->count(),
            'restocked' => OrderReturn::query()->where('restock', true)->count(),
            'not_restocked' => OrderReturn::query()->where('restock', false)->count(),
        ];

        return Inertia::render('Admin/Returns/Index', [
            'returns' => $returns,
            'filters' => [
                'search' => $search,
                'restock' => $restock,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Deep-link helper: returns are owned by orders and already rendered inline
     * on the order detail page, so we redirect there with a hash for context.
     */
    public function show(OrderReturn $orderReturn): RedirectResponse
    {
        return redirect()->away(
            route('admin.orders.show', $orderReturn->order_id).'#return-'.$orderReturn->id,
        );
    }
}
