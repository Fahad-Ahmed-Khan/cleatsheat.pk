<?php

namespace App\Jobs;

use App\Domain\Notifications\WhatsApp\OrderConfirmationService;
use App\Models\User;
use App\Models\WhatsAppInboundMessage;
use App\Models\WhatsAppSetting;
use App\Support\Sentry\ExceptionLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Parses an inbound WhatsApp payload and routes it.
 *
 *  - "interactive.button_reply" / "button" → resolve order from `id` prefix
 *    `order:{id}:confirm` or `order:{id}:cancel` and call OrderConfirmationService.
 *  - Plain text matching the marketing opt-out keyword → mark user opted out.
 *  - Anything else → recorded as `unrelated`. Admin can read these from the Inbox page.
 *
 * Cloud API envelopes are pre-unwrapped by WhatsAppWebhookController so this
 * job always receives a single `value` payload.
 */
class ProcessIncomingWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 90];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly array $payload) {}

    public function handle(OrderConfirmationService $confirmations): void
    {
        $messages = $this->extractMessages();

        if ($messages === []) {
            return;
        }

        foreach ($messages as $msg) {
            $this->processMessage($msg, $confirmations);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractMessages(): array
    {
        $list = [];

        // Cloud API single-value payload.
        if (isset($this->payload['messages']) && is_array($this->payload['messages'])) {
            foreach ($this->payload['messages'] as $m) {
                if (is_array($m)) {
                    $list[] = $m;
                }
            }
        } elseif (isset($this->payload['from']) && (isset($this->payload['text']) || isset($this->payload['body']))) {
            // Bridge / Twilio-flat payload.
            $list[] = $this->payload;
        }

        return $list;
    }

    /**
     * @param  array<string, mixed>  $msg
     */
    private function processMessage(array $msg, OrderConfirmationService $confirmations): void
    {
        $waId = isset($msg['id']) ? (string) $msg['id'] : null;
        $from = (string) ($msg['from'] ?? $msg['From'] ?? '');
        $type = (string) ($msg['type'] ?? 'text');

        if ($waId !== null && $waId !== '') {
            $exists = WhatsAppInboundMessage::query()->where('wa_message_id', $waId)->exists();
            if ($exists) {
                return;
            }
        }

        [$bodyText, $buttonPayload] = $this->extractBodyAndButton($msg, $type);

        $row = WhatsAppInboundMessage::query()->create([
            'wa_message_id' => $waId,
            'from_number' => mb_substr($from, 0, 32),
            'to_number' => null,
            'type' => mb_substr($type, 0, 24),
            'body' => $bodyText,
            'button_payload' => $buttonPayload,
            'payload' => $msg,
            'received_at' => now(),
        ]);

        try {
            $this->routeMessage($row, $buttonPayload, $bodyText, $confirmations);
            $row->processed_at = now();
            $row->save();
        } catch (\Throwable $e) {
            $row->handled_as = 'error';
            $row->handler_notes = mb_substr($e->getMessage(), 0, 240);
            $row->processed_at = now();
            $row->save();

            ExceptionLogging::report($e, 'whatsapp.inbound.routing_failed', ['id' => $row->id]);
        }
    }

    /**
     * @param  array<string, mixed>  $msg
     * @return array{0: ?string, 1: ?string}
     */
    private function extractBodyAndButton(array $msg, string $type): array
    {
        $body = null;
        $btnPayload = null;

        if ($type === 'text') {
            $body = isset($msg['text']) && is_array($msg['text'])
                ? (string) ($msg['text']['body'] ?? '')
                : (string) ($msg['Body'] ?? $msg['body'] ?? '');
            if ($body === '') {
                $body = null;
            }
        }

        if ($type === 'interactive' && is_array($msg['interactive'] ?? null)) {
            $interactive = $msg['interactive'];
            if (isset($interactive['button_reply']) && is_array($interactive['button_reply'])) {
                $btnPayload = (string) ($interactive['button_reply']['id'] ?? '');
                $body = (string) ($interactive['button_reply']['title'] ?? null) ?: $body;
            } elseif (isset($interactive['list_reply']) && is_array($interactive['list_reply'])) {
                $btnPayload = (string) ($interactive['list_reply']['id'] ?? '');
                $body = (string) ($interactive['list_reply']['title'] ?? null) ?: $body;
            }
        }

        if ($type === 'button' && is_array($msg['button'] ?? null)) {
            $btnPayload = (string) ($msg['button']['payload'] ?? '');
            $body = (string) ($msg['button']['text'] ?? null) ?: $body;
        }

        if ($btnPayload === '') {
            $btnPayload = null;
        }

        return [$body, $btnPayload];
    }

    private function routeMessage(
        WhatsAppInboundMessage $row,
        ?string $buttonPayload,
        ?string $bodyText,
        OrderConfirmationService $confirmations,
    ): void {
        if ($buttonPayload !== null && str_starts_with($buttonPayload, 'order:')) {
            $parts = explode(':', $buttonPayload);
            $orderId = isset($parts[1]) ? (int) $parts[1] : 0;
            $action = $parts[2] ?? '';

            if ($orderId > 0 && in_array($action, ['confirm', 'cancel'], true)) {
                $row->order_id = $orderId;

                if ($action === 'confirm') {
                    $outcome = $confirmations->confirmByButton($orderId, $row->from_number);
                    $row->handled_as = 'confirmation_yes';
                    $row->handler_notes = $outcome;
                } else {
                    $outcome = $confirmations->cancelByButton($orderId, $row->from_number);
                    $row->handled_as = 'confirmation_no';
                    $row->handler_notes = $outcome;
                }

                return;
            }
        }

        if ($bodyText !== null) {
            $settings = WhatsAppSetting::current();
            $keyword = strtoupper(trim((string) ($settings->marketing_opt_out_keyword ?? 'STOP')));
            $normalized = strtoupper(trim($bodyText));

            if ($keyword !== '' && $normalized === $keyword) {
                $this->markOptedOut($row->from_number);
                $row->handled_as = 'opt_out';
                $row->handler_notes = 'Marked user opted out.';

                return;
            }
        }

        $row->handled_as = 'unrelated';
    }

    private function markOptedOut(string $rawPhone): void
    {
        $digits = preg_replace('/\D+/', '', $rawPhone) ?? '';
        if ($digits === '') {
            return;
        }

        $local = $digits;
        if (str_starts_with($digits, '92') && strlen($digits) >= 11) {
            $local = '0'.substr($digits, 2);
        }

        $candidates = array_unique([$rawPhone, '+'.$digits, $digits, $local]);

        User::query()
            ->where(function ($q) use ($candidates): void {
                foreach ($candidates as $c) {
                    $q->orWhere('phone', $c);
                }
            })
            ->update([
                'whatsapp_opted_out' => true,
                'whatsapp_opted_out_at' => now(),
            ]);
    }
}
