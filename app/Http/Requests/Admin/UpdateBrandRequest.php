<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
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
        $brand = $this->route('brand');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('brands', 'slug')->ignore($brand->id),
            ],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:512'],
            'logo' => ['nullable', 'file', 'image', 'max:2048'],
        ];
    }
}
