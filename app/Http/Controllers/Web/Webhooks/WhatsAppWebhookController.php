<?php

namespace App\Http\Controllers\Web\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingWhatsAppJob;
use App\Models\WhatsAppSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives inbound webhooks from the WhatsApp Cloud API (Meta).
 *
 * GET /webhooks/whatsapp  -> Meta verification challenge (returns hub.challenge plaintext).
 * POST /webhooks/whatsapp -> message + status events. Verifies the signature when configured,
 *                            persists the raw payload via a queued job, returns 200 quickly so
 *                            Meta doesn't retry the message.
 */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = (string) $request->query('hub_mode', '');
        $token = (string) $request->query('hub_verify_token', '');
        $challenge = (string) $request->query('hub_challenge', '');

        $expected = $this->expectedVerifyToken();

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            return response($challenge, 200, ['Content-Type' => 'text/plain']);
        }

        Log::warning('whatsapp.webhook.verify_failed', [
            'mode' => $mode,
            'has_expected_token' => $expected !== '',
        ]);

        return response('Forbidden', 403);
    }

    public function handle(Request $request): Response
    {
        $payload = $request->all();

        if (! $this->verifySignature($request)) {
            Log::warning('whatsapp.webhook.bad_signature', [
                'ip' => $request->ip(),
            ]);

            return response('Forbidden', 403);
        }

        // Cloud API webhooks deliver a single envelope with one or many entries.
        // We dispatch a job per "value" payload so each inbound message is
        // processed independently and retried on failure.
        try {
            $entries = is_array($payload['entry'] ?? null) ? $payload['entry'] : [];
            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $changes = is_array($entry['changes'] ?? null) ? $entry['changes'] : [];
                foreach ($changes as $change) {
                    if (! is_array($change)) {
                        continue;
                    }
                    $value = is_array($change['value'] ?? null) ? $change['value'] : null;
                    if ($value === null) {
                        continue;
                    }
                    ProcessIncomingWhatsAppJob::dispatch($value);
                }
            }

            if ($entries === []) {
                // Non-Meta payload (bridge / Twilio-style) — process root.
                ProcessIncomingWhatsAppJob::dispatch($payload);
            }
        } catch (\Throwable $e) {
            Log::error('whatsapp.webhook.dispatch_failed', [
                'message' => $e->getMessage(),
            ]);
        }

        return response('OK', 200);
    }

    private function expectedVerifyToken(): string
    {
        try {
            $row = WhatsAppSetting::current();
            $tok = (string) ($row->cloud_webhook_verify_token ?? '');
            if ($tok !== '') {
                return $tok;
            }
        } catch (\Throwable) {
            // DB may be unavailable during bootstrap; fall through to config.
        }

        return (string) config('whatsapp.cloud.webhook_verify_token', env('WHATSAPP_CLOUD_WEBHOOK_VERIFY_TOKEN', ''));
    }

    private function verifySignature(Request $request): bool
    {
        $secret = (string) config('whatsapp.cloud.app_secret', env('WHATSAPP_CLOUD_APP_SECRET', ''));
        if ($secret === '') {
            // No signature verification configured (dev/sandbox). Allow.
            return true;
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');
        if ($header === '' || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $provided = substr($header, 7);
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $provided);
    }
}
