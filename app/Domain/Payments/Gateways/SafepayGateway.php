<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGatewayInterface;
use App\Domain\Payments\PaymentCallbackResult;
use App\Domain\Payments\PaymentInitResult;
use App\Domain\Payments\Safepay\SafepayClientFactory;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Safepay\Checkout;
use Safepay\Exception\ApiErrorException;
use Throwable;

/**
 * Safepay Express Checkout gateway.
 *
 * Flow:
 *  1. initiate(): create payment tracker + passport TBT via API, build hosted checkout URL.
 *  2. Shopper completes payment on Safepay's hosted page, then is redirected back to
 *     /payments/callback/safepay?tracker=track_xxx.
 *  3. verifyCallback(): re-fetch the tracker through Safepay's reporter API. We trust only
 *     this server-to-server response, never the query string the browser brings back.
 *  4. parseCallback(): map the tracker state to a normalised PaymentCallbackResult.
 *
 * Definitive payment confirmation lives in the webhook handler (`payment.succeeded` event).
 * The browser callback is best-effort UX and idempotent with respect to the webhook.
 */
class SafepayGateway implements PaymentGatewayInterface
{
    private const TRACKER_ENDED = 'TRACKER_ENDED';

    public function __construct(
        private readonly SafepayClientFactory $clientFactory,
    ) {}

    public function code(): string
    {
        return 'safepay';
    }

    public function initiate(Order $order, Payment $payment): PaymentInitResult
    {
        $cfg = (array) config('payments.gateways.safepay');
        $merchantApiKey = (string) ($cfg['merchant_api_key'] ?? '');
        $intent = (string) ($cfg['intent'] ?? 'CYBERSOURCE');
        $currency = strtoupper((string) ($cfg['currency'] ?? 'PKR'));
        $includeFees = (bool) ($cfg['include_fees'] ?? false);

        if ($merchantApiKey === '') {
            throw new RuntimeException('Safepay merchant API key is not configured.');
        }

        $amountMinor = $this->toMinorUnits((string) $order->grand_total);

        $client = $this->clientFactory->make();
        $environment = $this->clientFactory->environment();

        try {
            $session = $client->order->setup([
                'merchant_api_key' => $merchantApiKey,
                'intent' => $intent,
                'mode' => 'payment',
                'currency' => $currency,
                'amount' => $amountMinor,
                'include_fees' => $includeFees,
            ]);

            $trackerToken = (string) ($session->tracker->token ?? '');
            if ($trackerToken === '') {
                throw new RuntimeException('Safepay did not return a tracker token.');
            }

            // Attach our order number to the tracker so dashboard reconciliation
            // shows the local reference. Safepay only accepts a fixed set of meta
            // keys (currently `source` and `order_id`), so we keep the payload
            // minimal and tolerate failures - the canonical link is still the
            // tracker token stored on `payment.external_id`.
            try {
                $client->order->metadata($trackerToken, [
                    'data' => [
                        'source' => 'tryino-web',
                        'order_id' => $order->order_number,
                    ],
                ]);
            } catch (ApiErrorException $e) {
                Log::notice('payment.safepay.metadata_attach_failed', [
                    'order_id' => $order->id,
                    'tracker' => $trackerToken,
                    'http_status' => $e->getHttpStatus(),
                    'message' => $e->getMessage(),
                ]);
            }

            $tbt = $client->passport->create();
            $tbtToken = (string) ($tbt->token ?? '');
            if ($tbtToken === '') {
                throw new RuntimeException('Safepay did not return a passport token.');
            }

            $returnUrl = URL::route('payments.callback', ['gateway' => $this->code()]);

            $checkoutUrl = Checkout::constructURL([
                'environment' => $environment,
                'tracker' => $trackerToken,
                'tbt' => $tbtToken,
                'source' => 'hosted',
                'redirect_url' => $returnUrl,
                'cancel_url' => $returnUrl,
            ]);
        } catch (ApiErrorException $e) {
            Log::warning('payment.safepay.initiate_api_error', [
                'order_id' => $order->id,
                'http_status' => $e->getHttpStatus(),
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException('Could not start Safepay checkout: '.$e->getMessage(), previous: $e);
        }

        $payment->external_id = $trackerToken;
        $payment->meta = array_merge($payment->meta ?? [], [
            'tracker_token' => $trackerToken,
            'environment' => $environment,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'intent' => $intent,
            'checkout_url' => $checkoutUrl,
        ]);
        $payment->save();

        return new PaymentInitResult(
            immediateSuccess: false,
            redirectUrl: $checkoutUrl,
            meta: ['message' => 'Redirecting to Safepay to complete payment.'],
        );
    }

    public function redirectFormFields(Payment $payment): array
    {
        // Safepay returns a direct hosted URL; no intermediate auto-post form is used.
        return [];
    }

    public function redirectSubmitUrl(): string
    {
        return '';
    }

    public function verifyCallback(Request $request): bool
    {
        $tracker = $this->extractTrackerToken($request);
        if ($tracker === null) {
            Log::notice('payment.safepay.callback_missing_tracker', [
                'query' => $request->query(),
            ]);

            return false;
        }

        try {
            $remote = $this->clientFactory->make()->reporter->retrieve($tracker);
        } catch (ApiErrorException $e) {
            Log::warning('payment.safepay.callback_lookup_failed', [
                'tracker' => $tracker,
                'http_status' => $e->getHttpStatus(),
                'message' => $e->getMessage(),
            ]);

            return false;
        } catch (Throwable $e) {
            Log::warning('payment.safepay.callback_lookup_exception', [
                'tracker' => $tracker,
                'message' => $e->getMessage(),
            ]);

            return false;
        }

        $payload = $this->normaliseTrackerPayload($remote);
        if ($payload === null) {
            return false;
        }

        // Cache the verified response so parseCallback() does not re-fetch.
        $request->attributes->set('safepay_verified_tracker', $payload);

        return true;
    }

    public function parseCallback(Request $request): PaymentCallbackResult
    {
        /** @var array<string, mixed>|null $verified */
        $verified = $request->attributes->get('safepay_verified_tracker');
        $tracker = $this->extractTrackerToken($request);

        if (! is_array($verified)) {
            return new PaymentCallbackResult(false, null, $tracker, 'Could not verify Safepay tracker.', $request->all());
        }

        $orderNumber = $this->resolveOrderNumber($tracker, $verified);
        $state = $this->extractState($verified);
        $success = $state === self::TRACKER_ENDED;

        if ($orderNumber === null || $orderNumber === '') {
            return new PaymentCallbackResult(false, null, $tracker, 'Missing order reference on Safepay tracker.', $verified);
        }

        return new PaymentCallbackResult(
            $success,
            $orderNumber,
            $tracker,
            $success ? null : ('Payment did not complete (state: '.($state !== '' ? $state : 'unknown').').'),
            $verified,
        );
    }

    /**
     * Convert a PKR decimal string into Safepay's minor units (paisa).
     */
    public function toMinorUnits(string $grandTotalPkr): int
    {
        return (int) round(((float) $grandTotalPkr) * 100);
    }

    private function extractTrackerToken(Request $request): ?string
    {
        $candidates = [
            $request->query('tracker'),
            $request->query('tracker_token'),
            $request->input('tracker'),
            $request->input('tracker_token'),
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Safepay's reporter API returns the tracker as a flat object (token + state at
     * the top level). Older docs occasionally show a nested `data` or `tracker`
     * wrapper, so we accept both — anything with a `state` or `token` field is
     * considered a usable payload.
     *
     * @return array<string, mixed>|null
     */
    private function normaliseTrackerPayload(mixed $remote): ?array
    {
        try {
            $encoded = json_encode($remote, JSON_THROW_ON_ERROR);
            $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            Log::warning('payment.safepay.callback_decode_failed', ['message' => $e->getMessage()]);

            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $decoded = $decoded['data'];
        }

        if (isset($decoded['tracker']) && is_array($decoded['tracker'])) {
            return $decoded;
        }

        if (isset($decoded['state']) || isset($decoded['token'])) {
            return ['tracker' => $decoded];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $verified
     */
    private function extractState(array $verified): string
    {
        $candidates = [
            $verified['tracker']['state'] ?? null,
            $verified['state'] ?? null,
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Resolve our local order number from the Safepay tracker payload. Falls back to
     * looking it up by the persisted external_id when metadata is unavailable.
     *
     * @param  array<string, mixed>  $verified
     */
    private function resolveOrderNumber(?string $trackerToken, array $verified): ?string
    {
        $metadata = $verified['tracker']['metadata']
            ?? $verified['metadata']
            ?? null;
        if (is_array($metadata)) {
            foreach (['order_id', 'order_number'] as $key) {
                $entry = $metadata[$key] ?? ($metadata['data'][$key] ?? null);

                // Reporter returns each metadata field as `{ key, value, token, ... }`.
                if (is_array($entry) && isset($entry['value']) && is_string($entry['value']) && $entry['value'] !== '') {
                    return $entry['value'];
                }

                // Legacy / setup-time shape passes plain strings.
                if (is_string($entry) && $entry !== '') {
                    return $entry;
                }
            }
        }

        if ($trackerToken !== null && $trackerToken !== '') {
            $payment = Payment::query()
                ->where('gateway', $this->code())
                ->where('external_id', $trackerToken)
                ->with('order')
                ->latest('id')
                ->first();
            if ($payment !== null && $payment->order !== null) {
                return (string) $payment->order->order_number;
            }
        }

        return null;
    }
}
