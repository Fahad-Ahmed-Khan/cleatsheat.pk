<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMarketingSettingsRequest extends FormRequest
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
            'home_meta_title' => ['nullable', 'string', 'max:255'],
            'home_meta_description' => ['nullable', 'string', 'max:512'],
            'default_og_image_url' => ['nullable', 'string', 'max:1024'],
            'twitter_site' => ['nullable', 'string', 'max:64'],

            'ga4_enabled' => ['boolean'],
            'ga4_measurement_id' => ['nullable', 'string', 'max:64'],

            'meta_pixel_enabled' => ['boolean'],
            'meta_pixel_id' => ['nullable', 'string', 'max:32'],

            'tiktok_pixel_enabled' => ['boolean'],
            'tiktok_pixel_id' => ['nullable', 'string', 'max:64'],

            'robots_mode' => ['required', Rule::in(['allow_all', 'custom'])],
            'robots_custom' => ['nullable', 'string', 'max:8000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ga4_enabled' => filter_var($this->input('ga4_enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'meta_pixel_enabled' => filter_var($this->input('meta_pixel_enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'tiktok_pixel_enabled' => filter_var($this->input('tiktok_pixel_enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
        ]);
    }
}
