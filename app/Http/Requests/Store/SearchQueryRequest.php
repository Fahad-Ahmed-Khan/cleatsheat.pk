<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class SearchQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $max = (int) config('store.search_query_max_length', 120);

        return [
            'q' => ['nullable', 'string', 'max:'.$max, 'regex:/^[\pL\pN\s\-\'\.]+$/u'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer'],
            'brand_ids' => ['sometimes', 'array'],
            'brand_ids.*' => ['integer'],
            'color_ids' => ['sometimes', 'array'],
            'color_ids.*' => ['integer'],
            'gender' => ['nullable', 'string', 'max:32'],
            'type' => ['nullable', 'string', 'max:64'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'size' => ['nullable', 'string', 'max:32'],
            'size_uk' => ['sometimes', 'array'],
            'size_uk.*' => ['string', 'max:16'],
            'availability' => ['nullable', 'string', 'in:in_stock'],
            'sort' => ['nullable', 'string', 'in:price_asc,price_desc,newest'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.regex' => 'Search may only contain letters, numbers, spaces, hyphens, apostrophes, and periods.',
        ];
    }
}
