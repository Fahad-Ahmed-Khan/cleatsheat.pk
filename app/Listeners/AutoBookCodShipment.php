<?php

namespace App\Listeners;

use App\Enums\ShipmentStatus;
use App\Events\OrderCreated;
use App\Jobs\BookShipmentJob;
use App\Models\ShippingSetting;
use App\Models\WhatsAppSetting;

class AutoBookCodShipment
{
    public function handle(OrderCreated $event): void
    {
        $settings = ShippingSetting::current();
        if (! $settings->auto_book_cod_orders) {
            return;
        }

        $order = $event->order->load('shipments');

        if (! is_string($order->payment_gateway) || ! str_contains(strtolower($order->payment_gateway), 'cod')) {
            return;
        }

        // If COD WhatsApp confirmation is enabled, wait for the customer to tap "Confirm"
        // before booking the shipment. Without confirmation we'd ship orders the customer
        // never agreed to — historically the #1 driver of refused-delivery losses.
        $wa = WhatsAppSetting::current();
        if ($wa->enabled_cod_confirmation && $order->confirmed_at === null) {
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
