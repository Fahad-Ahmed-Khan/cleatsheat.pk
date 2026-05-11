<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGatewayInterface;
use App\Domain\Payments\PaymentCallbackResult;
use App\Domain\Payments\PaymentInitResult;
use App\Domain\Payments\Support\SortedHmacSigner;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

/**
 * Hosted Easypaisa-style checkout: auto-post form with HMAC integrity.
 * Replace submit URL and field names with values from your Easypaisa merchant agreement if they differ.
 */
class EasypaisaGateway implements PaymentGatewayInterface
{
    private const HASH_FIELD = 'secure_hash';

    public function code(): string
    {
        return 'easypaisa';
    }

    public function initiate(Order $order, Payment $payment): PaymentInitResult
    {
        $cfg = config('payments.gateways.easypaisa');
        $merchantId = (string) ($cfg['merchant_id'] ?? '');
        $hashKey = (string) ($cfg['hash_key'] ?? '');
        $amountStr = $this->formatAmount((string) $order->grand_total, (bool) ($cfg['amount_in_paisa'] ?? false));

        $fields = [
            'merchant_id' => $merchantId,
            'store_id' => (string) ($cfg['store_id'] ?? ''),
            'order_ref' => $order->order_number,
            'amount' => $amountStr,
            'currency' => 'PKR',
            'bill_reference' => 'Tryino '.$order->order_number,
        ];

        if ($hashKey !== '') {
            $fields[self::HASH_FIELD] = SortedHmacSigner::sign($fields, self::HASH_FIELD, $hashKey);
        }

        $payment->external_id = 'ep_'.$order->order_number.'_'.$payment->id;
        $payment->meta = array_merge($payment->meta ?? [], [
            'init_fields' => $fields,
            'callback_expected_order_ref' => $order->order_number,
        ]);
        $payment->save();

        $ttl = (int) config('payments.redirect_token_ttl_minutes', 60);
        $token = Crypt::encryptString(json_encode([
            'payment_id' => $payment->id,
            'exp' => now()->addMinutes($ttl)->getTimestamp(),
        ], JSON_THROW_ON_ERROR));

        $redirectUrl = URL::route('payments.redirect.form', ['gateway' => $this->code(), 'token' => $token]);

        return new PaymentInitResult(
            immediateSuccess: false,
            redirectUrl: $redirectUrl,
            meta: ['message' => 'Redirecting to Easypaisa to complete payment.'],
        );
    }

    public function redirectFormFields(Payment $payment): array
    {
        $fields = $payment->meta['init_fields'] ?? [];

        return array_map(static fn ($v) => (string) $v, $fields);
    }

    public function redirectSubmitUrl(): string
    {
        return (string) config('payments.gateways.easypaisa.submit_url', '');
    }

    public function verifyCallback(Request $request): bool
    {
        $hashKey = (string) config('payments.gateways.easypaisa.hash_key', '');
        if ($hashKey === '') {
            return false;
        }

        return SortedHmacSigner::verify($request->all(), self::HASH_FIELD, $hashKey);
    }

    public function parseCallback(Request $request): PaymentCallbackResult
    {
        $orderNumber = $request->input('order_ref')
            ?? $request->input('orderReference')
            ?? $request->input('merchant_order_id');
        $txn = $request->input('transaction_id')
            ?? $request->input('txn_id')
            ?? $request->input('bank_transaction_id');
        $respCode = trim((string) $request->input('resp_code', ''));
        $statusRaw = strtolower((string) ($request->input('payment_status')
            ?? $request->input('status')
            ?? ''));

        $success = false;
        $reason = null;

        if ($respCode === '000' || strtoupper($respCode) === 'SUCCESS') {
            $success = true;
        }

        if (! $success && $statusRaw !== '') {
            $successTokens = ['success', 'paid', 'completed', 'ok'];
            foreach ($successTokens as $t) {
                if (str_contains($statusRaw, $t)) {
                    $success = true;
                    break;
                }
            }
        }

        if (! $success && $statusRaw !== '') {
            foreach (['failed', 'declined', 'cancel', 'error'] as $t) {
                if (str_contains($statusRaw, $t)) {
                    $reason = 'Payment was not completed.';
                    break;
                }
            }
        }

        if ($orderNumber === null || $orderNumber === '') {
            return new PaymentCallbackResult(false, null, is_string($txn) ? $txn : null, 'Missing order reference', $request->all());
        }

        return new PaymentCallbackResult(
            $success,
            (string) $orderNumber,
            is_string($txn) ? $txn : null,
            $success ? null : ($reason ?? 'Payment failed or was cancelled.'),
            $request->all(),
        );
    }

    private function formatAmount(string $grandTotalPkr, bool $usePaisa): string
    {
        if ($usePaisa) {
            return (string) (int) round((float) $grandTotalPkr * 100);
        }

        return number_format((float) $grandTotalPkr, 2, '.', '');
    }
}
