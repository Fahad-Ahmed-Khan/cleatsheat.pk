<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StartBargainSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'customer_name' => ['required', 'string', 'max:80'],
            'customer_phone' => ['required', 'string', 'max:32'],
            'guest_token' => ['nullable', 'string', 'uuid'],
        ];
    }
}
