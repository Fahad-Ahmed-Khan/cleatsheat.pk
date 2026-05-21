<?php

namespace App\Observers;

use App\Domain\Notifications\WhatsApp\WhatsAppTemplates;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Jobs\BookShipmentJob;
use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\Order;
use App\Models\ShippingSetting;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if ($order->wasChanged('payment_status') && $order->payment_status === PaymentStatus::Paid) {
            $this->maybeAutoBookShipment($order);
            SendWhatsAppNotificationJob::dispatch($order->id, 'payment_received', 'customer');
        }

        if ($order->wasChanged('status')) {
            /** @var string|null $fromRaw */
            $fromRaw = $order->getOriginal('status');
            $from = is_string($fromRaw) ? OrderStatus::from($fromRaw) : null;

            // COD orders that just transitioned to Confirmed should book immediately
            // (the auto-book listener intentionally skipped them at checkout when
            // WhatsApp confirmation was enabled). Order_confirmed WA is fired by
            // OrderConfirmationService, not here, to keep manual-confirmation flows
            // consistent.
            if ($order->status === OrderStatus::Confirmed && $from !== OrderStatus::Confirmed) {
                $this->maybeAutoBookCodShipment($order);
            }

            $templateKey = WhatsAppTemplates::keyForStatusTransition($from, $order->status);
            if ($templateKey !== null) {
                SendWhatsAppNotificationJob::dispatch($order->id, $templateKey, 'customer');
            }
        }
    }

    private function maybeAutoBookShipment(Order $order): void
    {
        $settings = ShippingSetting::current();
        if (! $settings->auto_book_on_payment_confirmed) {
            return;
        }

        $shipment = $order->shipments()->first();
        if ($shipment === null || $shipment->courier_id === null) {
            return;
        }

        if ($shipment->status !== ShipmentStatus::Pending) {
            return;
        }

        BookShipmentJob::dispatch($shipment->id);
    }

    private function maybeAutoBookCodShipment(Order $order): void
    {
        if (! is_string($order->payment_gateway) || ! str_contains(strtolower($order->payment_gateway), 'cod')) {
            return;
        }

        $settings = ShippingSetting::current();
        if (! $settings->auto_book_cod_orders) {
            return;
        }

        $shipment = $order->shipments()->first();
        if ($shipment === null || $shipment->courier_id === null) {
            return;
        }

        if ($shipment->status !== ShipmentStatus::Pending) {
            return;
        }

        BookShipmentJob::dispatch($shipment->id);
    }
}
