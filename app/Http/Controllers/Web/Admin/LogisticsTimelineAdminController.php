<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\ShipmentEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class LogisticsTimelineAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $courierId = $request->integer('courier_id');
        $status = $request->input('status') ?: null;
        $dateFrom = $request->input('date_from') ?: null;
        $dateTo = $request->input('date_to') ?: null;

        $perPage = (int) $request->input('per_page', 50);
        if ($perPage <= 0 || $perPage > 200) {
            $perPage = 50;
        }

        $events = ShipmentEvent::query()
            ->with(['shipment.courier:id,name,adapter', 'shipment.order:id,order_number'])
            ->when($courierId > 0, function ($q) use ($courierId) {
                $q->whereHas('shipment', fn ($sq) => $sq->where('courier_id', $courierId));
            })
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->when($dateFrom, function ($q, $v) {
                try {
                    $q->where('occurred_at', '>=', Carbon::parse($v)->startOfDay());
                } catch (\Throwable) {
                }
            })
            ->when($dateTo, function ($q, $v) {
                try {
                    $q->where('occurred_at', '<=', Carbon::parse($v)->endOfDay());
                } catch (\Throwable) {
                }
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $events->through(fn (ShipmentEvent $e): array => [
            'id' => $e->id,
            'shipment_id' => $e->shipment_id,
            'status' => $e->status,
            'description' => $e->description,
            'occurred_at' => $e->occurred_at?->toIso8601String(),
            'occurred_at_human' => $e->occurred_at?->format('M j, Y H:i'),
            'order_id' => $e->shipment?->order_id,
            'order_number' => $e->shipment?->order?->order_number,
            'tracking_number' => $e->shipment?->tracking_number,
            'courier_name' => $e->shipment?->courier?->name,
            'courier_adapter' => $e->shipment?->courier?->adapter,
        ]);

        $couriers = Courier::query()->orderBy('name')->get(['id', 'name', 'adapter']);

        $statuses = ShipmentEvent::query()->distinct()->orderBy('status')->pluck('status')->all();

        return Inertia::render('Admin/Logistics/Timeline', [
            'events' => $events,
            'couriers' => $couriers,
            'statuses' => $statuses,
            'filters' => [
                'courier_id' => $courierId > 0 ? $courierId : null,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
            ],
        ]);
    }
}
