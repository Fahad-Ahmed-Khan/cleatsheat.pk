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
 * JazzCash merchant hosted form (Mobile Wallet / Cards) with integrity salt verification on return/IPN.
 */
class JazzCashGateway implements PaymentGatewayInterface
{
    private const HASH_FIELD = 'pp_SecureHash';

    public function code(): string
    {
        return 'jazzcash';
    }

    public function initiate(Order $order, Payment $payment): PaymentInitResult
    {
        $cfg = config('payments.gateways.jazzcash');
        $merchantId = (string) ($cfg['merchant_id'] ?? '');
        $password = (string) ($cfg['password'] ?? '');
        $salt = (string) ($cfg['integrity_salt'] ?? '');
        $usePaisa = (bool) ($cfg['amount_in_paisa'] ?? true);

        $amountStr = $this->formatAmount((string) $order->grand_total, $usePaisa);
        $datetime = now()->format('YmdHis');

        $fields = [
            'pp_Amount' => $amountStr,
            'pp_BillReference' => 'bill_'.$order->order_number,
            'pp_Description' => 'Order '.$order->order_number,
            'pp_Language' => 'EN',
            'pp_MerchantID' => $merchantId,
            'pp_Password' => $password,
            'pp_ReturnURL' => URL::route('payments.callback', ['gateway' => $this->code()]),
            'pp_TxnCurrency' => 'PKR',
            'pp_TxnDateTime' => $datetime,
            'pp_TxnExpiryDateTime' => now()->addMinutes(30)->format('YmdHis'),
            'pp_TxnRefNo' => 'TXN_'.$order->order_number.'_'.$payment->id,
        ];

        if ($salt !== '') {
            $fields[self::HASH_FIELD] = SortedHmacSigner::sign($fields, self::HASH_FIELD, $salt);
        }

        $payment->external_id = (string) $fields['pp_TxnRefNo'];
        $payment->meta = array_merge($payment->meta ?? [], [
            'init_fields' => $fields,
            'callback_expected_order_number' => $order->order_number,
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
            meta: ['message' => 'Redirecting to JazzCash to complete payment.'],
        );
    }

    public function redirectFormFields(Payment $payment): array
    {
        $fields = $payment->meta['init_fields'] ?? [];

        return array_map(static fn ($v) => (string) $v, $fields);
    }

    public function redirectSubmitUrl(): string
    {
        return (string) config('payments.gateways.jazzcash.submit_url', '');
    }

    public function verifyCallback(Request $request): bool
    {
        $salt = (string) config('payments.gateways.jazzcash.integrity_salt', '');
        if ($salt === '') {
            return false;
        }

        return SortedHmacSigner::verify($request->all(), self::HASH_FIELD, $salt);
    }

    public function parseCallback(Request $request): PaymentCallbackResult
    {
        $billRef = (string) $request->input('pp_BillReference', '');
        $orderNumber = null;
        if (str_starts_with($billRef, 'bill_')) {
            $orderNumber = substr($billRef, strlen('bill_'));
        }

        $txn = $request->input('pp_TxnRefNo');
        $code = strtoupper((string) $request->input('pp_ResponseCode', ''));

        $successCodes = ['000', '001', 'TXN_SUCCESS'];
        $success = $code !== '' && (in_array($code, $successCodes, true)
            || str_contains($code, 'SUCCESS'));

        $reason = $success ? null : (string) $request->input('pp_ResponseMessage', 'Payment failed or was cancelled.');

        if ($orderNumber === null || $orderNumber === '') {
            return new PaymentCallbackResult(false, null, is_string($txn) ? $txn : null, 'Missing order reference', $request->all());
        }

        return new PaymentCallbackResult(
            $success,
            $orderNumber,
            is_string($txn) ? $txn : null,
            $reason,
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
