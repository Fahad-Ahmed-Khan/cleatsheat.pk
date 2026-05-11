<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkPrintLabelsRequest extends FormRequest
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
            'layout' => ['nullable', 'string', Rule::in(['one_per_page', 'three_per_a4'])],
            'paper_size' => [
                Rule::requiredIf(fn () => ($this->input('layout') ?: 'one_per_page') === 'one_per_page'),
                'nullable',
                'string',
                Rule::in(['a6', 'a5']),
            ],
        ];
    }
}

