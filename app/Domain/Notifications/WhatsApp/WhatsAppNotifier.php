<?php

namespace App\Domain\Notifications\WhatsApp;

use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\WhatsAppSetting;
use Illuminate\Support\Facades\Log;

class WhatsAppNotifier
{
    public function __construct(
        private readonly WhatsAppClient $client,
    ) {}

    public function send(Order $order, string $templateKey, string $audience = 'customer', ?string $overrideRecipient = null): void
    {
        $settings = WhatsAppSetting::current();

        if ($audience === 'customer' && ! $settings->enabled_customer_notifications) {
            return;
        }

        if ($audience === 'admin' && ! $settings->enabled_admin_notifications) {
            return;
        }

        $recipient = $overrideRecipient ?? $this->resolveCustomerRecipient($order);
        if ($recipient === null) {
            return;
        }

        $normalized = $this->normalizePakE164($recipient);
        if ($normalized === null) {
            $this->logFailure($templateKey, $recipient, $audience, 'Invalid phone number format.', []);

            return;
        }

        $rendered = $audience === 'admin' && $templateKey === 'admin_new_order'
            ? ['short' => 'New order', 'body' => WhatsAppTemplates::adminNewOrder($order)]
            : WhatsAppTemplates::render($templateKey, $order);

        $payload = $this->buildPayload($normalized, $rendered['body'], $templateKey, $order, $rendered['short']);

        try {
            $response = $this->dispatchHttp($payload);

            NotificationLog::query()->create([
                'channel' => 'whatsapp',
                'recipient' => $normalized,
                'template_key' => $templateKey,
                'payload' => [
                    'audience' => $audience,
                    'request' => $payload,
                    'response' => $response,
                ],
                'status' => 'sent',
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            $this->logFailure($templateKey, $normalized, $audience, $e->getMessage(), [
                'audience' => $audience,
                'request' => $payload,
            ]);

            Log::error('whatsapp.send_failed', [
                'order_id' => $order->id,
                'template' => $templateKey,
                'audience' => $audience,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function resolveCustomerRecipient(Order $order): ?string
    {
        $phone = $order->shipping_address_snapshot['phone'] ?? null;

        return is_string($phone) && $phone !== '' ? $phone : null;
    }

    private function normalizePakE164(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '92')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+92'.substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '+92'.$digits;
        }

        if (str_starts_with($digits, '1') && strlen($digits) >= 11) {
            return '+'.$digits;
        }

        return '+'.$digits;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function dispatchHttp(array $payload): array
    {
        $cloudEnabled = (bool) config('whatsapp.cloud.enabled', false);

        if ($cloudEnabled) {
            return $this->client->sendCloudMessage($payload);
        }

        return $this->client->sendBridgeMessage($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(string $toE164, string $bodyText, string $templateKey, Order $order, string $shortStatus): array
    {
        $cloudEnabled = (bool) config('whatsapp.cloud.enabled', false);

        if ($cloudEnabled) {
            // Admin alerts are usually longer than a 4-parameter utility template; use a Cloud text message.
            if ($templateKey === 'admin_new_order') {
                return [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => ltrim($toE164, '+'),
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $bodyText,
                    ],
                ];
            }

            $tpl = (array) (config('whatsapp.cloud.templates.'.$templateKey) ?? []);
            $name = (string) ($tpl['name'] ?? '');

            if ($name !== '') {
                $lang = (string) ($tpl['language'] ?? 'en_US');

                $nameVal = (string) ($order->shipping_address_snapshot['full_name'] ?? 'Customer');
                $orderNo = (string) $order->order_number;
                $total = (string) $order->grand_total;

                return [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => ltrim($toE164, '+'),
                    'type' => 'template',
                    'template' => [
                        'name' => $name,
                        'language' => ['code' => $lang],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $nameVal],
                                    ['type' => 'text', 'text' => $orderNo],
                                    ['type' => 'text', 'text' => $total],
                                    ['type' => 'text', 'text' => $shortStatus],
                                ],
                            ],
                        ],
                    ],
                ];
            }

            // Cloud API without configured template names: use a text message (best-effort).
            return [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => ltrim($toE164, '+'),
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $bodyText,
                ],
            ];
        }

        return [
            'to' => $toE164,
            'body' => $bodyText,
            'template_key' => $templateKey,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'from' => config('whatsapp.bridge.from_number'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logFailure(string $templateKey, string $recipient, string $audience, string $message, array $payload): void
    {
        NotificationLog::query()->create([
            'channel' => 'whatsapp',
            'recipient' => mb_substr($recipient, 0, 128),
            'template_key' => $templateKey,
            'payload' => array_merge($payload, ['audience' => $audience]),
            'status' => 'failed',
            'error_message' => $message,
        ]);
    }
}
