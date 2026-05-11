<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'fallback_online_failed_to_cod' => ['sometimes', 'boolean'],
            'methods' => ['required', 'array'],
            'methods.*.id' => ['required', 'integer', 'exists:payment_method_configs,id'],
            'methods.*.enabled' => ['sometimes', 'boolean'],
            'methods.*.customer_label' => ['required', 'string', 'max:255'],
            'methods.*.fee_fixed' => ['required', 'numeric', 'min:0'],
            'methods.*.fee_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'methods.*.sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fallback_online_failed_to_cod' => $this->boolean('fallback_online_failed_to_cod'),
        ]);
    }
}
