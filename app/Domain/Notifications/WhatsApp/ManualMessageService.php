<?php

namespace App\Domain\Notifications\WhatsApp;

use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\CourierRider;
use App\Models\Order;
use App\Models\User;
use App\Models\WhatsAppSetting;
use App\Models\WhatsAppTemplate;

class ManualMessageService
{
    public function __construct(

        private readonly WhatsAppNotifier $notifier,

        private readonly TemplateRepository $templates,

    ) {}

    /**
     * @return array{ok:bool, message:string}
     */
    public function sendToOrder(Order $order, ?string $templateKey, ?string $freeText): array
    {

        if (! WhatsAppSetting::current()->enabled_customer_notifications) {

            return ['ok' => false, 'message' => 'Customer WhatsApp notifications are disabled in settings.'];

        }

        $phone = (string) ($order->shipping_address_snapshot['phone'] ?? '');

        if ($phone === '') {

            return ['ok' => false, 'message' => 'Order has no customer phone on the shipping address.'];

        }

        if ($templateKey !== null && $templateKey !== '') {

            SendWhatsAppNotificationJob::dispatchSync($order->id, $templateKey, 'customer');

            return ['ok' => true, 'message' => 'Template message sent.'];

        }

        if ($freeText === null || trim($freeText) === '') {

            return ['ok' => false, 'message' => 'Choose a template or enter a message.'];

        }

        $ok = $this->notifier->sendArbitrary($phone, trim($freeText), 'manual', 'customer', $order);

        return $ok

            ? ['ok' => true, 'message' => 'Message sent.']

            : ['ok' => false, 'message' => 'Send failed — check notification logs.'];

    }

    /**
     * @return array{ok:bool, message:string}
     */
    public function sendToUser(User $user, ?string $templateKey, ?string $freeText): array
    {

        if (! WhatsAppSetting::current()->enabled_customer_notifications) {

            return ['ok' => false, 'message' => 'Customer WhatsApp notifications are disabled in settings.'];

        }

        $phone = (string) ($user->phone ?? '');

        if ($phone === '') {

            return ['ok' => false, 'message' => 'Customer has no phone number on file.'];

        }

        $latestOrder = $user->orders()->latest()->first();

        if ($templateKey !== null && $templateKey !== '' && $latestOrder !== null) {

            SendWhatsAppNotificationJob::dispatchSync($latestOrder->id, $templateKey, 'customer');

            return ['ok' => true, 'message' => 'Template message sent using their latest order for placeholders.'];

        }

        if ($freeText === null || trim($freeText) === '') {

            return ['ok' => false, 'message' => 'Choose a template or enter a message.'];

        }

        $body = $latestOrder !== null

            ? $this->templates->renderPlaceholders(trim($freeText), $latestOrder)

            : trim($freeText);

        $ok = $this->notifier->sendArbitrary($phone, $body, 'manual', 'customer', $latestOrder);

        return $ok

            ? ['ok' => true, 'message' => 'Message sent.']

            : ['ok' => false, 'message' => 'Send failed — check notification logs.'];

    }

    /**
     * @return array{ok:bool, message:string}
     */
    public function sendToRider(CourierRider $rider, ?string $templateKey, ?string $freeText): array
    {

        $phone = (string) $rider->phone;

        if ($phone === '') {

            return ['ok' => false, 'message' => 'Rider has no phone number.'];

        }

        if ($templateKey !== null && $templateKey !== '') {

            $tpl = WhatsAppTemplate::findActiveByKey($templateKey);

            $body = $tpl?->body ?? $freeText ?? '';

            if ($body === '') {

                return ['ok' => false, 'message' => 'Template not found or empty.'];

            }

            $ok = $this->notifier->sendArbitrary($phone, $body, $templateKey, 'rider');

            return $ok

                ? ['ok' => true, 'message' => 'Template message sent to rider.']

                : ['ok' => false, 'message' => 'Send failed — check notification logs.'];

        }

        if ($freeText === null || trim($freeText) === '') {

            return ['ok' => false, 'message' => 'Choose a template or enter a message.'];

        }

        $ok = $this->notifier->sendArbitrary($phone, trim($freeText), 'manual', 'rider');

        return $ok

            ? ['ok' => true, 'message' => 'Message sent to rider.']

            : ['ok' => false, 'message' => 'Send failed — check notification logs.'];

    }

    /**
     * @return list<array{key:string, label:string}>
     */
    public function customerTemplateOptions(): array
    {

        return WhatsAppTemplate::query()

            ->where('is_active', true)

            ->where('audience', 'customer')

            ->orderBy('key')

            ->get(['key', 'label'])

            ->map(fn (WhatsAppTemplate $t) => ['key' => $t->key, 'label' => $t->label ?: $t->key])

            ->values()

            ->all();

    }

    /**
     * @return list<array{key:string, label:string}>
     */
    public function riderTemplateOptions(): array
    {

        return WhatsAppTemplate::query()

            ->where('is_active', true)

            ->where('audience', 'rider')

            ->orderBy('key')

            ->get(['key', 'label'])

            ->map(fn (WhatsAppTemplate $t) => ['key' => $t->key, 'label' => $t->label ?: $t->key])

            ->values()

            ->all();

    }
}
