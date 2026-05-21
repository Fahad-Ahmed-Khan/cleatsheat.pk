<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesCategoryParent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    use ValidatesCategoryParent;

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
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                Rule::notIn([$category->id]),
                $this->rootParentIdRule(),
                function (string $attribute, mixed $value, \Closure $fail) use ($category): void {
                    if ($value !== null && $category->children()->exists()) {
                        $fail('Parent categories with subcategories cannot be moved under another parent.');
                    }
                },
            ],
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
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'parent_id' => $this->filled('parent_id') ? (int) $this->input('parent_id') : null,
        ];
        if ($this->has('is_active')) {
            $merge['is_active'] = $this->boolean('is_active');
        }
        $this->merge($merge);
    }
}
