<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
        $category = $this->route('category');

        return [
            'parent_id' => ['nullable', 'integer', 'exists:categories,id', Rule::notIn([$category->id])],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('categories', 'slug')->ignore($category->id),
            ],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:512'],
            'og_image_url' => ['nullable', 'string', 'max:1024'],
            'intro_html' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_id' => $this->filled('parent_id') ? (int) $this->input('parent_id') : null,
        ]);
    }
}
