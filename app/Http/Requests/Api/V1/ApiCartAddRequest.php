<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ApiCartAddRequest extends FormRequest
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
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'size_label' => ['required', 'string', 'max:32'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'bargain_phone' => ['nullable', 'string', 'max:32'],
        ];
    }
}
