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
}
