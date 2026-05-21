<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Coupon;
use App\Models\Courier;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\SizeChart;
use App\Models\VariantSize;
use App\Models\WhatsAppInboundMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class AdminDashboardMetrics
{
    /**
     * @return array<string, mixed>
     */
    public static function build(): array
    {
        $now = now();

        return [
            'counts' => self::catalogCounts(),
            'kpis' => self::kpis($now),
            'charts' => self::charts($now),
            'recent_orders' => self::recentOrders(),
            'top_products' => self::topProducts($now),
            'logistics' => self::logisticsShipments(),
            'whatsapp' => self::whatsappTiles($now),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private static function whatsappTiles(\Illuminate\Support\Carbon $now): array
    {
        $todayStart = $now->copy()->startOfDay();

        $sentToday = (int) NotificationLog::query()
            ->where('channel', 'whatsapp')
            ->where('status', 'sent')
            ->where('created_at', '>=', $todayStart)
            ->count();

        $pendingCod = (int) Order::query()
            ->where('awaiting_confirmation', true)
            ->whereNot('status', OrderStatus::Cancelled)
            ->count();

        $deliveredShipments = (int) Shipment::query()
            ->where('status', ShipmentStatus::Delivered)
            ->count();
        $totalTerminal = (int) Shipment::query()
            ->whereIn('status', [ShipmentStatus::Delivered, ShipmentStatus::Failed, ShipmentStatus::Canceled])
            ->count();

        $deliveryRate = $totalTerminal > 0
            ? round(($deliveredShipments / $totalTerminal) * 100, 1)
            : 0.0;

        $inboundToday = (int) WhatsAppInboundMessage::query()
            ->where('received_at', '>=', $todayStart)
            ->count();

        return [
            'sent_today' => $sentToday,
            'pending_cod_confirmations' => $pendingCod,
            'delivery_rate_percent' => $deliveryRate,
            'inbound_today' => $inboundToday,
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function catalogCounts(): array
    {
        return [
            'products' => Product::query()->count(),
            'orders' => Order::query()->count(),
            'brands' => Brand::query()->count(),
            'categories' => Category::query()->count(),
            'colors' => Color::query()->count(),
            'size_charts' => SizeChart::query()->count(),
            'couriers' => Courier::query()->count(),
            'coupons' => Coupon::query()->count(),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private static function kpis(\Illuminate\Support\Carbon $now): array
    {
        $mtdStart = $now->copy()->startOfMonth();

        $paidMtd = (float) Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('created_at', '>=', $mtdStart)
            ->sum('grand_total');

        $gmvMtd = (float) Order::query()
            ->where('created_at', '>=', $mtdStart)
            ->whereNot('status', OrderStatus::Cancelled)
            ->sum('grand_total');

        $ordersMtd = (int) Order::query()
            ->where('created_at', '>=', $mtdStart)
            ->whereNot('status', OrderStatus::Cancelled)
            ->count();

        $paidOrdersMtd = (int) Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('created_at', '>=', $mtdStart)
            ->count();

        $avgOrder = $ordersMtd > 0 ? round($gmvMtd / $ordersMtd, 2) : 0.0;

        $stockUnits = (int) VariantSize::query()->sum('stock_qty');

        $lowStock = (int) VariantSize::query()
            ->where('stock_qty', '>', 0)
            ->whereColumn('stock_qty', '<=', 'low_stock_threshold')
            ->count();

        $outOfStock = (int) VariantSize::query()->where('stock_qty', '<=', 0)->count();

        $pendingShipments = (int) Shipment::query()
            ->whereIn('status', [ShipmentStatus::Pending, ShipmentStatus::Booked])
            ->count();

        $inTransit = (int) Shipment::query()
            ->where('status', ShipmentStatus::InTransit)
            ->count();

        // Today-scoped operational tiles. Used by Dashboard.vue with deep links
        // to the new Orders index presets shipped in Phase 2.2 (today, today + not booked,
        // booking_failed). cod_collected_today / cod_pending_today look at *delivered*
        // shipments today on COD orders.
        $todayStart = $now->copy()->startOfDay();

        $ordersToday = (int) Order::query()
            ->where('created_at', '>=', $todayStart)
            ->whereNot('status', OrderStatus::Cancelled)
            ->count();

        $codTodayBase = Shipment::query()
            ->where('delivered_at', '>=', $todayStart)
            ->where('status', ShipmentStatus::Delivered)
            ->whereHas('order', function ($q) {
                $q->where(function ($qq) {
                    $qq->where('payment_gateway', 'like', '%cod%')
                        ->orWhere('payment_gateway', 'like', '%cash%');
                });
            });

        $codCollectedToday = (float) (clone $codTodayBase)
            ->whereHas('order', fn ($q) => $q->where('payment_status', PaymentStatus::Paid))
            ->sum('cod_amount');

        $codPendingToday = (float) (clone $codTodayBase)
            ->whereHas('order', fn ($q) => $q->where('payment_status', '!=', PaymentStatus::Paid))
            ->sum('cod_amount');

        $bookingsFailedToday = (int) Shipment::query()
            ->where('status', ShipmentStatus::Failed)
            ->where(function ($q) use ($todayStart) {
                $q->where('failed_at', '>=', $todayStart)
                    ->orWhere(function ($qq) use ($todayStart) {
                        $qq->whereNull('failed_at')->where('updated_at', '>=', $todayStart);
                    });
            })
            ->count();

        return [
            'revenue_paid_mtd' => $paidMtd,
            'gmv_mtd' => $gmvMtd,
            'orders_mtd' => $ordersMtd,
            'paid_orders_mtd' => $paidOrdersMtd,
            'avg_order_value' => $avgOrder,
            'stock_total_units' => $stockUnits,
            'low_stock_variants' => $lowStock,
            'out_of_stock_variants' => $outOfStock,
            'shipments_pending_booked' => $pendingShipments,
            'shipments_in_transit' => $inTransit,
            'orders_today' => $ordersToday,
            'cod_collected_today' => $codCollectedToday,
            'cod_pending_today' => $codPendingToday,
            'bookings_failed_today' => $bookingsFailedToday,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function charts(\Illuminate\Support\Carbon $now): array
    {
        $start = $now->copy()->subDays(29)->startOfDay();
        $days = [];
        for ($i = 0; $i < 30; $i++) {
            $days[] = $start->copy()->addDays($i)->format('Y-m-d');
        }

        $byDay = [];
        foreach ($days as $d) {
            $byDay[$d] = ['revenue' => 0.0, 'fees' => 0.0, 'orders' => 0];
        }

        $orders = Order::query()
            ->where('created_at', '>=', $start)
            ->whereNot('status', OrderStatus::Cancelled)
            ->get(['created_at', 'grand_total', 'shipping_total', 'cod_fee']);

        foreach ($orders as $o) {
            $d = $o->created_at->format('Y-m-d');
            if (! isset($byDay[$d])) {
                continue;
            }
            $byDay[$d]['revenue'] += (float) $o->grand_total;
            $byDay[$d]['fees'] += (float) $o->shipping_total + (float) $o->cod_fee;
            $byDay[$d]['orders']++;
        }

        $categories = [];
        $revenueData = [];
        $feesData = [];
        $ordersData = [];
        foreach ($days as $d) {
            $categories[] = Carbon::parse($d)->format('d M');
            $revenueData[] = round($byDay[$d]['revenue'], 2);
            $feesData[] = round($byDay[$d]['fees'], 2);
            $ordersData[] = (int) $byDay[$d]['orders'];
        }

        $ordersByStatus = Order::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $orderStatusLabels = [];
        $orderStatusSeries = [];
        foreach ($ordersByStatus as $status => $count) {
            $orderStatusLabels[] = self::orderStatusLabel(is_string($status) ? $status : (string) $status);
            $orderStatusSeries[] = (int) $count;
        }

        $shipmentsByStatus = Shipment::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $shipLabels = [];
        $shipSeries = [];
        foreach ($shipmentsByStatus as $status => $count) {
            $shipLabels[] = self::shipmentStatusLabel(is_string($status) ? $status : (string) $status);
            $shipSeries[] = (int) $count;
        }

        return [
            'sales_daily' => [
                'categories' => $categories,
                'revenue' => $revenueData,
                'fulfillment_fees' => $feesData,
                'order_counts' => $ordersData,
            ],
            'orders_by_status' => [
                'labels' => $orderStatusLabels,
                'series' => $orderStatusSeries,
            ],
            'shipments_by_status' => [
                'labels' => $shipLabels,
                'series' => $shipSeries,
            ],
        ];
    }

    private static function orderStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => $status,
        };
    }

    private static function shipmentStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pending',
            'booked' => 'Booked',
            'in_transit' => 'In transit',
            'delivered' => 'Delivered',
            'failed' => 'Failed',
            'canceled' => 'Canceled',
            default => $status,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function recentOrders(): array
    {
        return Order::query()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status->value,
                'status_label' => self::orderStatusLabel($o->status->value),
                'payment_status' => $o->payment_status->value,
                'grand_total' => (string) $o->grand_total,
                'created_at' => $o->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function topProducts(\Illuminate\Support\Carbon $now): array
    {
        $since = $now->copy()->subDays(30)->startOfDay();

        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', $since)
            ->whereNot('orders.status', OrderStatus::Cancelled->value)
            ->selectRaw('order_items.product_name as product_name')
            ->selectRaw('order_items.sku as sku')
            ->selectRaw('SUM(order_items.quantity) as qty_sold')
            ->groupBy('order_items.product_name', 'order_items.sku')
            ->orderByDesc('qty_sold')
            ->limit(6)
            ->get();

        return $rows->map(fn ($r) => [
            'product_name' => $r->product_name,
            'sku' => $r->sku,
            'qty_sold' => (int) $r->qty_sold,
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function logisticsShipments(): array
    {
        return Shipment::query()
            ->with(['courier', 'order'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (Shipment $s) {
                $status = $s->status->value;

                return [
                    'id' => $s->id,
                    'status' => $status,
                    'status_label' => self::shipmentStatusLabel($status),
                    'tracking_number' => $s->tracking_number,
                    'courier_name' => $s->courier?->name ?? '—',
                    'order_number' => $s->order?->order_number ?? '—',
                    'order_id' => $s->order_id,
                    'updated_at' => $s->updated_at?->toIso8601String(),
                    'tab' => self::logisticsTab($s->status),
                ];
            })
            ->values()
            ->all();
    }

    private static function logisticsTab(ShipmentStatus $status): string
    {
        return match ($status) {
            ShipmentStatus::Pending, ShipmentStatus::Booked => 'new',
            ShipmentStatus::InTransit, ShipmentStatus::Failed => 'shipping',
            ShipmentStatus::Delivered, ShipmentStatus::Canceled => 'done',
        };
    }
}
