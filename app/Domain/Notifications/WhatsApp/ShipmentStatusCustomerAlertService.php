<?php

namespace App\Domain\Notifications\WhatsApp;

use App\Enums\ShipmentStatus;
use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\NotificationLog;
use App\Models\Shipment;
use App\Models\WhatsAppSetting;

/**
 * Sends customer WhatsApp alerts when courier tracking changes shipment status.

 * Decoupled from order status so admin workflow and courier webhooks stay independent.
 */
class ShipmentStatusCustomerAlertService
{
    public function notifyStatusTransition(Shipment $shipment, ShipmentStatus $previous, ShipmentStatus $next): void
    {

        $settings = WhatsAppSetting::current();

        if (! $settings->enabled_shipment_status_customer_alerts || ! $settings->enabled_customer_notifications) {

            return;

        }

        $shipment->loadMissing('order');

        $order = $shipment->order;

        if ($order === null) {

            return;

        }

        $templateKey = $this->templateForTransition($previous, $next);

        if ($templateKey === null) {

            return;

        }

        if ($this->alreadySentRecently($order->id, $templateKey, $shipment->id)) {

            return;

        }

        SendWhatsAppNotificationJob::dispatch($order->id, $templateKey, 'customer');

    }

    /**
     * Some couriers signal "out for delivery" in webhook text without a distinct enum value.
     */
    public function notifyOutForDeliveryIfApplicable(Shipment $shipment): void
    {

        $settings = WhatsAppSetting::current();

        if (! $settings->enabled_shipment_status_customer_alerts || ! $settings->enabled_customer_notifications) {

            return;

        }

        $shipment->loadMissing('order');

        $order = $shipment->order;

        if ($order === null) {

            return;

        }

        if ($this->alreadySentRecently($order->id, 'out_for_delivery', $shipment->id)) {

            return;

        }

        SendWhatsAppNotificationJob::dispatch($order->id, 'out_for_delivery', 'customer');

    }

    private function templateForTransition(ShipmentStatus $previous, ShipmentStatus $next): ?string
    {

        if ($previous === $next) {

            return null;

        }

        return match ($next) {

            ShipmentStatus::InTransit => $previous === ShipmentStatus::Booked || $previous === ShipmentStatus::Pending

                ? 'order_shipped'

                : null,

            ShipmentStatus::Delivered => 'order_delivered',

            ShipmentStatus::Failed => 'order_returned',

            default => null,

        };

    }

    private function alreadySentRecently(int $orderId, string $templateKey, int $shipmentId): bool
    {

        return NotificationLog::query()

            ->where('channel', 'whatsapp')

            ->where('template_key', $templateKey)

            ->where('status', 'sent')

            ->where('created_at', '>=', now()->subHours(24))

            ->where(function ($q) use ($orderId, $shipmentId): void {

                $q->where('payload->order_id', $orderId)

                    ->orWhere('payload->shipment_id', $shipmentId);

            })

            ->exists();

    }

}
