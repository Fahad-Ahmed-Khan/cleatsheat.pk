<?php

namespace App\Http\Requests\Api\V1;

use App\Models\PaymentMethodConfig;
use App\Support\Api\SanctumBearerUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApiCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
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
            'guest_email' => ['nullable', 'email', 'max:255', Rule::requiredIf(fn () => SanctumBearerUser::resolve($this) === null)],
            'payment_gateway' => ['required', 'string', Rule::in(PaymentMethodConfig::enabledCodes())],
        ];
    }
}
