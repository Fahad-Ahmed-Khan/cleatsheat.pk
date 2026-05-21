<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Notifications\WhatsApp\ManualMessageService;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAdminController extends Controller
{
    /**
     * Customers paid >= this value (PKR) over their lifetime to qualify as VIP.
     */
    private const VIP_SPEND_THRESHOLD = 50000;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $segment = (string) $request->input('segment', '');
        if (! in_array($segment, ['has_orders', 'no_orders', 'vip', ''], true)) {
            $segment = '';
        }

        $perPage = (int) $request->input('per_page', 25);
        if ($perPage <= 0 || $perPage > 100) {
            $perPage = 25;
        }

        $ordersBaseSql = Order::query()
            ->whereColumn('orders.user_id', 'users.id')
            ->whereNot('status', OrderStatus::Cancelled);

        $query = User::query()
            ->where('role', UserRole::Customer)
            ->addSelect([
                'orders_count' => (clone $ordersBaseSql)->selectRaw('COUNT(*)'),
                'lifetime_spend' => (clone $ordersBaseSql)->selectRaw('COALESCE(SUM(grand_total), 0)'),
                'last_order_at' => (clone $ordersBaseSql)->selectRaw('MAX(created_at)'),
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('email', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            });

        match ($segment) {
            'has_orders' => $query->whereHas('orders'),
            'no_orders' => $query->whereDoesntHave('orders'),
            'vip' => $query->whereHas('orders', function ($oq) {
                $oq->whereNot('status', OrderStatus::Cancelled)
                    ->selectRaw('user_id, SUM(grand_total) as total_spend')
                    ->groupBy('user_id')
                    ->havingRaw('SUM(grand_total) >= ?', [self::VIP_SPEND_THRESHOLD]);
            }),
            default => null,
        };

        $customers = $query
            ->orderByDesc('lifetime_spend')
            ->orderByDesc('orders_count')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $customers->through(fn (User $u): array => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone ?? null,
            'orders_count' => (int) ($u->orders_count ?? 0),
            'lifetime_spend' => (float) ($u->lifetime_spend ?? 0),
            'last_order_at' => $u->last_order_at,
            'created_at' => $u->created_at?->toIso8601String(),
            'created_at_human' => $u->created_at?->format('M j, Y'),
        ]);

        $stats = [
            'total' => User::query()->where('role', UserRole::Customer)->count(),
            'with_orders' => User::query()->where('role', UserRole::Customer)->whereHas('orders')->count(),
            'guest_orders' => Order::query()->whereNull('user_id')->count(),
        ];

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'segment' => $segment,
                'per_page' => $perPage,
            ],
            'stats' => $stats,
            'vip_threshold' => self::VIP_SPEND_THRESHOLD,
        ]);
    }

    public function show(User $customer, ManualMessageService $manual): Response
    {
        abort_unless($customer->role === UserRole::Customer, 404);

        $orders = Order::query()
            ->where('user_id', $customer->id)
            ->latest()
            ->limit(15)
            ->get(['id', 'order_number', 'status', 'grand_total', 'created_at']);

        return Inertia::render('Admin/Customers/Show', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'whatsapp_opted_out' => (bool) $customer->whatsapp_opted_out,
                'whatsapp_opted_out_at' => $customer->whatsapp_opted_out_at?->toIso8601String(),
                'created_at' => $customer->created_at?->format('M j, Y'),
            ],
            'orders' => $orders->map(fn (Order $o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status->value,
                'grand_total' => (float) $o->grand_total,
                'created_at' => $o->created_at?->format('M j, Y'),
            ]),
            'whatsapp_templates' => $manual->customerTemplateOptions(),
            'whatsapp_send_route' => route('admin.customers.whatsapp.send', $customer),
        ]);
    }
}
