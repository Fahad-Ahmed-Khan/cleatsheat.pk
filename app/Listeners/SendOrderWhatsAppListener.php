<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\WhatsAppSetting;

class SendOrderWhatsAppListener
{
    public function handle(OrderCreated $event): void
    {
        $orderId = $event->order->id;

        SendWhatsAppNotificationJob::dispatch($orderId, 'order_placed', 'customer');

        $settings = WhatsAppSetting::current();
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
