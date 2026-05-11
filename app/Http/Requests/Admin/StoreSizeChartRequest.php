<?php

namespace App\Http\Requests\Admin;

use App\Enums\Gender;
use App\Enums\ShoeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSizeChartRequest extends FormRequest
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
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'shoe_type' => ['nullable', Rule::enum(ShoeType::class)],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'rows.*.label' => ['nullable', 'string', 'max:64'],
            'rows.*.uk_size' => ['nullable', 'string', 'max:16'],
            'rows.*.eu_size' => ['nullable', 'string', 'max:16'],
            'rows.*.pk_size' => ['nullable', 'string', 'max:16'],
            'rows.*.foot_cm' => ['nullable', 'numeric', 'min:0'],
            'rows.*.measurements' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'gender' => $this->input('gender') ?: null,
            'shoe_type' => $this->input('shoe_type') ?: null,
        ]);
    }
}
