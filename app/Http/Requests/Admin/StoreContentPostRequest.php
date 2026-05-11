<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreContentPostRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/', 'unique:content_posts,slug'],
            'title' => ['required', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:512'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string'],
            'pillar_keyword' => ['nullable', 'string', 'max:128'],
            'is_published' => ['boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_published' => filter_var($this->input('is_published'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
        ]);

        if ($this->input('published_at') === '' || $this->input('published_at') === null) {
            $this->merge(['published_at' => null]);
        }

        if ($this->boolean('is_published') && ! $this->filled('published_at')) {
            $this->merge(['published_at' => now()->toDateTimeString()]);
        }
    }
}
