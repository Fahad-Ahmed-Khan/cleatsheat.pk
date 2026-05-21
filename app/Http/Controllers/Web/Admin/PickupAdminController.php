<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Shipping\Pickup\PickupDispatchService;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\CourierRider;
use App\Models\PickupDispatch;
use App\Models\Shipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PickupAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $rawDate = (string) $request->query('date', '');
        try {
            $date = $rawDate !== '' ? Carbon::parse($rawDate)->startOfDay() : Carbon::now()->startOfDay();
        } catch (\Throwable) {
            $date = Carbon::now()->startOfDay();
        }

        $couriers = Courier::query()->active()->orderBy('name')->get(['id', 'name', 'code']);

        $tally = [];
        foreach ($couriers as $courier) {
            $shipments = Shipment::query()
                ->where('courier_id', $courier->id)
                ->whereIn('status', [ShipmentStatus::Booked->value])
                ->whereDate('booked_at', $date->toDateString())
                ->get(['id', 'tracking_number', 'cod_amount', 'status']);

            $rider = CourierRider::query()
                ->where('courier_id', $courier->id)
                ->where('is_active', true)
                ->orderByDesc('is_primary')
                ->first();

            $existing = PickupDispatch::query()
                ->with('rider:id,name,phone')
                ->where('courier_id', $courier->id)
                ->whereDate('dispatch_date', $date->toDateString())
                ->orderByDesc('id')
                ->get()
                ->map(fn (PickupDispatch $d): array => [
                    'id' => $d->id,
                    'parcel_count' => $d->parcel_count,
                    'cod_total' => $d->cod_total,
                    'sent_via' => $d->sent_via,
                    'status' => $d->status,
                    'sent_at' => $d->sent_at?->format('M j, Y H:i'),
                    'rider' => $d->rider ? ['name' => $d->rider->name, 'phone' => $d->rider->phone] : null,
                    'error_message' => $d->error_message,
                ])
                ->all();

            $tally[] = [
                'courier' => [
                    'id' => $courier->id,
                    'name' => $courier->name,
                    'code' => $courier->code,
                ],
                'rider' => $rider ? [
                    'id' => $rider->id,
                    'name' => $rider->name,
                    'phone' => $rider->phone,
                    'is_primary' => (bool) $rider->is_primary,
                ] : null,
                'parcel_count' => $shipments->count(),
                'cod_total' => (float) $shipments->sum(fn (Shipment $s) => (float) ($s->cod_amount ?? 0)),
                'tracking_numbers' => $shipments->pluck('tracking_number')->filter()->values()->all(),
                'dispatches' => $existing,
            ];
        }

        return Inertia::render('Admin/Pickups/Index', [
            'tally' => $tally,
            'date' => $date->toDateString(),
        ]);
    }

    public function send(Request $request, PickupDispatchService $service): RedirectResponse
    {
        $data = $request->validate([
            'courier_id' => ['required', 'integer', 'exists:couriers,id'],
            'date' => ['nullable', 'date'],
        ]);

        $courier = Courier::query()->findOrFail($data['courier_id']);
        $date = isset($data['date']) ? Carbon::parse($data['date']) : Carbon::now();

        $row = $service->dispatchForCourier($courier, $date, sentVia: 'manual');

        if ($row === null) {
            return back()->with('error', 'Nothing to dispatch for '.$courier->name.' on this date.');
        }

        return back()->with($row->status === 'sent' ? 'status' : 'error',
            $row->status === 'sent'
                ? 'Pickup notice sent to '.$courier->name.'.'
                : ('Pickup notice failed: '.$row->error_message));
    }
}
