<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkBookShipmentsRequest extends FormRequest
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
            'mode' => ['required', 'string', Rule::in(['auto', 'manual'])],
            'courier_id' => [
                Rule::requiredIf(fn () => $this->input('mode') === 'manual'),
                'nullable',
                'integer',
                'exists:couriers,id',
            ],
        ];
    }
}

