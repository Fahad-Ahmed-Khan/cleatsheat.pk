<?php

namespace App\Listeners;

use App\Enums\ShipmentStatus;
use App\Events\OrderCreated;
use App\Jobs\BookShipmentJob;
use App\Models\ShippingSetting;

class AutoBookCodShipment
{
    public function handle(OrderCreated $event): void
    {
        $settings = ShippingSetting::current();
        if (! $settings->auto_book_cod_orders) {
            return;
        }

        $order = $event->order->load('shipments');

        if ($order->payment_gateway !== 'cod') {
            return;
        }

        $shipment = $order->shipments->first();
        if ($shipment === null || $shipment->courier_id === null) {
            return;
        }

        if ($shipment->status !== ShipmentStatus::Pending) {
            return;
        }

        BookShipmentJob::dispatch($shipment->id);
    }
}
