<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\BookShipmentJob;
use App\Jobs\SyncShipmentTrackingJob;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderShipmentController extends Controller
{
    public function book(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('manageShipping', $order);

        $shipment = $order->shipments()->firstOrFail();
        BookShipmentJob::dispatch($shipment->id);

        return back()->with('status', 'Shipment booking has been queued.');
    }

    public function syncTracking(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('manageShipping', $order);

        $queued = 0;
        foreach ($order->shipments as $shipment) {
            if ($shipment->tracking_number) {
                SyncShipmentTrackingJob::dispatch($shipment->id);
                $queued++;
            }
        }

        return back()->with('status', $queued > 0
            ? "Queued {$queued} tracking sync job(s)."
            : 'No tracking numbers to sync yet.');
    }
}
