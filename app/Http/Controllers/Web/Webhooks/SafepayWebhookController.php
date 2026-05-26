<?php

namespace App\Http\Controllers\Web\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSafepayWebhookJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Safepay\Exception\SignatureVerificationException;
use Safepay\Webhook;
use Throwable;

/**
 * Receives Safepay payment webhooks (payment.succeeded / payment.failed / payment.refunded).
 *
 * Safepay signs the raw request body with HMAC-SHA512 using the merchant's webhook
 * secret and ships the digest in the `X-SFPY-SIGNATURE` header. We verify here and
 * push the parsed Event to a queue job so we can return 200 within Safepay's
 * 10-second deadline regardless of how slow downstream finalisation is.
 */
class SafepayWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $secret = (string) config('payments.gateways.safepay.webhook_secret', '');
        if ($secret === '') {
            Log::warning('payment.safepay.webhook.unconfigured');

            return response('Safepay webhook secret is not configured.', 503);
        }

        $signature = (string) $request->header('X-SFPY-SIGNATURE', '');
        if ($signature === '') {
            return response('Missing signature', 400);
        }

        $payload = $request->getContent();

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('payment.safepay.webhook.bad_signature', [
                'ip' => $request->ip(),
                'message' => $e->getMessage(),
            ]);

            return response('Invalid signature', 400);
        } catch (Throwable $e) {
            Log::warning('payment.safepay.webhook.invalid_payload', [
                'message' => $e->getMessage(),
            ]);

            return response('Invalid payload', 400);
        }

        $type = (string) ($event->type ?? '');
        $decoded = json_decode($payload, true);
        $eventData = is_array($decoded) ? $decoded : [];

        ProcessSafepayWebhookJob::dispatch($type, $eventData);

        return response('OK', 200);
    }
}
