<?php

namespace App\Http\Requests\Store;

use App\Models\PaymentMethodConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'line1' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'guest_email' => ['nullable', 'email', 'max:255', Rule::requiredIf(fn () => ! $this->user())],
            'payment_gateway' => ['required', 'string', Rule::in(PaymentMethodConfig::enabledCodes())],
        ];
    }
}
