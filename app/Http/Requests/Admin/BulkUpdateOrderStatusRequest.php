<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateOrderStatusRequest extends FormRequest
{
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
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
        ];
    }

    /**
     * Ensure at least one of status/payment_status is present.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $hasStatus = $this->filled('status');
            $hasPayment = $this->filled('payment_status');
            if (! $hasStatus && ! $hasPayment) {
                $v->errors()->add('status', 'Provide at least one of status or payment_status.');
            }
        });
    }
}

