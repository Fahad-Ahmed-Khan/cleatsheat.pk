<?php

namespace App\Domain\Marketing;

use App\Domain\Notifications\WhatsApp\WhatsAppClient;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\NotificationLog;

class AbandonedCartReminderService
{
    public function __construct(
        private readonly WhatsAppClient $client,
    ) {}

    /**
     * Best-effort WhatsApp reminder for an abandoned cart.
     *
     * Returns an outcome string so the controller can render per-cart feedback
     * inside a bulk_summary modal:
     *   - "sent"           message handed off successfully
     *   - "no_phone"       cart has no user with a usable phone number
     *   - "send_failed"    the API call threw
     */
    public function send(Cart $cart): string
    {
        $cart->loadMissing(['user', 'items.variant.product']);

        $rawPhone = (string) ($cart->user?->phone ?? '');
        $recipient = $this->normalizePakE164($rawPhone);
        if ($recipient === null) {
            return 'no_phone';
        }

        $body = $this->buildBody($cart);

        $payload = $this->buildPayload($recipient, $body);

        try {
            $response = (bool) config('whatsapp.cloud.enabled', false)
                ? $this->client->sendCloudMessage($payload)
                : $this->client->sendBridgeMessage($payload);

            NotificationLog::query()->create([
                'channel' => 'whatsapp',
                'recipient' => $recipient,
                'template_key' => 'abandoned_cart_reminder',
                'payload' => [
                    'audience' => 'customer',
                    'cart_id' => $cart->id,
                    'request' => $payload,
                    'response' => $response,
                ],
                'status' => 'sent',
                'error_message' => null,
            ]);

            return 'sent';
        } catch (\Throwable $e) {
            NotificationLog::query()->create([
                'channel' => 'whatsapp',
                'recipient' => $recipient,
                'template_key' => 'abandoned_cart_reminder',
                'payload' => [
                    'audience' => 'customer',
                    'cart_id' => $cart->id,
                    'request' => $payload,
                ],
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return 'send_failed';
        }
    }

    private function buildBody(Cart $cart): string
    {
        $name = (string) ($cart->user?->name ?? 'there');
        $subtotal = (float) $cart->items->sum(
            fn (CartItem $i) => (float) $i->unit_price_snapshot * (int) $i->quantity,
        );
        $first = $cart->items->first();
        $hint = $first?->variant?->product?->name ?? 'your selected item';

        $currency = $cart->currency ?? 'PKR';

        return sprintf(
            "Hi %s — you left %s in your bag (around %s %s). Tap to finish checkout and we'll keep it reserved for a bit longer.",
            $name,
            $hint,
            number_format($subtotal, 0, '.', ','),
            $currency,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(string $toE164, string $bodyText): array
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
            'template_key' => 'abandoned_cart_reminder',
            'from' => config('whatsapp.bridge.from_number'),
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

        return '+'.$digits;
    }
}
