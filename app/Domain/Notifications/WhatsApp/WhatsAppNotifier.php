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
        private readonly TemplateRepository $templates,
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

        $template = $audience === 'admin' && $templateKey === 'admin_new_order'
            ? $this->resolveAdminTemplate($order)
            : $this->templates->resolve($templateKey, $order);

        $payload = $this->buildPayload($normalized, $template, $order);

        try {
            $response = $this->dispatchHttp($payload);

            $waMessageId = $this->extractMessageId($response);

            if ($templateKey === 'order_placed_cod_confirm') {
                $order->confirmation_sent_at = now();
                $order->awaiting_confirmation = true;
                $order->save();
            }

            NotificationLog::query()->create([
                'channel' => 'whatsapp',
                'recipient' => $normalized,
                'template_key' => $templateKey,
                'wa_message_id' => $waMessageId,
                'payload' => [
                    'audience' => $audience,
                    'order_id' => $order->id,
                    'request' => $payload,
                    'response' => $response,
                ],
                'status' => 'sent',
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            $this->logFailure($templateKey, $normalized, $audience, $e->getMessage(), [
                'audience' => $audience,
                'order_id' => $order->id,
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

    /**
     * Send a free-form message to an arbitrary recipient (used for manual sends,
     * pickup notices, and campaigns). Pass an order if you want placeholders
     * substituted; otherwise the body is sent as-is.
     */
    public function sendArbitrary(string $recipient, string $body, string $templateKey = 'manual', string $audience = 'customer', ?Order $order = null, ?int $campaignId = null): bool
    {
        $normalized = $this->normalizePakE164($recipient);
        if ($normalized === null) {
            $this->logFailure($templateKey, $recipient, $audience, 'Invalid phone number format.', []);

            return false;
        }

        $renderedBody = $order !== null ? $this->templates->renderPlaceholders($body, $order) : $body;

        $payload = $this->buildTextPayload($normalized, $renderedBody, $templateKey, $order);

        try {
            $response = $this->dispatchHttp($payload);

            NotificationLog::query()->create([
                'channel' => 'whatsapp',
                'recipient' => $normalized,
                'template_key' => $templateKey,
                'wa_message_id' => $this->extractMessageId($response),
                'campaign_id' => $campaignId,
                'payload' => [
                    'audience' => $audience,
                    'order_id' => $order?->id,
                    'request' => $payload,
                    'response' => $response,
                ],
                'status' => 'sent',
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logFailure($templateKey, $normalized, $audience, $e->getMessage(), [
                'audience' => $audience,
                'order_id' => $order?->id,
                'campaign_id' => $campaignId,
                'request' => $payload,
            ]);

            return false;
        }
    }

    private function resolveCustomerRecipient(Order $order): ?string
    {
        $phone = $order->shipping_address_snapshot['phone'] ?? null;

        return is_string($phone) && $phone !== '' ? $phone : null;
    }

    /**
     * @return array{short:string, body:string, has_buttons:bool, button_payloads:array, cloud_template_name:?string, cloud_template_language:string, key:string}
     */
    private function resolveAdminTemplate(Order $order): array
    {
        $body = WhatsAppTemplates::adminNewOrder($order);

        return [
            'short' => 'New order',
            'body' => $body,
            'has_buttons' => false,
            'button_payloads' => [],
            'cloud_template_name' => null,
            'cloud_template_language' => 'en_US',
            'key' => 'admin_new_order',
        ];
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
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    private function buildPayload(string $toE164, array $template, Order $order): array
    {
        $cloudEnabled = (bool) config('whatsapp.cloud.enabled', false);

        if ($cloudEnabled && $template['has_buttons'] && $template['button_payloads'] !== []) {
            return InteractiveMessageBuilder::buttonPayload($toE164, $template['body'], $template['button_payloads']);
        }

        if ($cloudEnabled && (string) $template['cloud_template_name'] !== '') {
            return InteractiveMessageBuilder::cloudTemplatePayload(
                $toE164,
                $template['cloud_template_name'],
                $template['cloud_template_language'],
                $order,
                $template['short'],
            );
        }

        return $this->buildTextPayload($toE164, $template['body'], $template['key'], $order);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTextPayload(string $toE164, string $bodyText, string $templateKey, ?Order $order): array
    {
        $cloudEnabled = (bool) config('whatsapp.cloud.enabled', false);

        if ($cloudEnabled) {
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
            'order_id' => $order?->id,
            'order_number' => $order?->order_number,
            'from' => config('whatsapp.bridge.from_number'),
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractMessageId(array $response): ?string
    {
        if (isset($response['messages']) && is_array($response['messages'])) {
            $first = $response['messages'][0] ?? null;
            if (is_array($first) && isset($first['id'])) {
                return (string) $first['id'];
            }
        }

        if (isset($response['id']) && is_string($response['id'])) {
            return $response['id'];
        }

        return null;
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
