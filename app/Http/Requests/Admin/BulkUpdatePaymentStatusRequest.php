<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdatePaymentStatusRequest extends FormRequest
{
    /**
     * Payment transitions that require an explicit admin override.
     *
     * Switching to these states forces ledger side-effects (refund/cancel)
     * or marks money as collected without a PSP settlement event, so we
     * require a typed reason + override checkbox before accepting them.
     *
     * @var array<int, string>
     */
    public const DESTRUCTIVE_TRANSITIONS = [
        'paid',
        'refunded',
        'canceled',
    ];

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer', 'distinct', 'exists:orders,id'],
            'payment_status' => ['required', Rule::enum(PaymentStatus::class)],
            'override' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $target = (string) $this->input('payment_status', '');
            if (! in_array($target, self::DESTRUCTIVE_TRANSITIONS, true)) {
                return;
            }

            if (! $this->boolean('override')) {
                $v->errors()->add('override', 'Confirm the override checkbox to apply destructive payment transitions.');
            }

            $reason = trim((string) $this->input('reason', ''));
            if ($reason === '') {
                $v->errors()->add('reason', 'A reason is required for destructive payment transitions.');
            }
        });
    }
}
