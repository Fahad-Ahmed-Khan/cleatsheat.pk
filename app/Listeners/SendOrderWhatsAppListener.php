<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\WhatsAppSetting;

class SendOrderWhatsAppListener
{
    public function handle(OrderCreated $event): void
    {
        $order = $event->order;
        $orderId = $order->id;

        $settings = WhatsAppSetting::current();

        $isCod = is_string($order->payment_gateway) && str_contains(strtolower($order->payment_gateway), 'cod');

        $customerTemplate = ($isCod && $settings->enabled_cod_confirmation)
            ? 'order_placed_cod_confirm'
            : 'order_placed';

        SendWhatsAppNotificationJob::dispatch($orderId, $customerTemplate, 'customer');

        if (! $settings->enabled_admin_notifications) {
            return;
        }

        foreach ($settings->admin_recipients ?? [] as $recipient) {
            if (! is_string($recipient)) {
                continue;
            }
            $recipient = trim($recipient);
            if ($recipient === '') {
                continue;
            }

            SendWhatsAppNotificationJob::dispatch($orderId, 'admin_new_order', 'admin', $recipient);
        }
    }
}
