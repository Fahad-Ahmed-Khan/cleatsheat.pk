<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerReviewRequest extends FormRequest
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
            'author_name' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'quote' => ['required', 'string', 'min:10', 'max:2000'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quote.min' => 'Please share a bit more detail about your experience.',
            'quote.max' => 'Your feedback is too long. Please keep it under 2,000 characters.',
        ];
    }
}
