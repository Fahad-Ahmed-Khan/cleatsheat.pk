<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStorefrontSettingsRequest extends FormRequest
{
    /** @var list<string> */
    private const UPLOAD_KEYS = [
        'logo',
        'logo_dark',
        'favicon',
        'hero_image',
        'promo_banner_image',
        'default_og_image',
    ];

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<int, string|ValidationRule>
     */
    private function optionalImageRulesFor(string $field, int $maxKb): array
    {
        return [
            Rule::when(
                $this->hasFile($field),
                ['file', 'image', "max:{$maxKb}"],
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $hex = ['nullable', 'string', 'regex:/^#?[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/'];

        return [
            'site_name' => ['nullable', 'string', 'max:120'],
            'logo' => $this->optionalImageRulesFor('logo', 2048),
            'logo_dark' => $this->optionalImageRulesFor('logo_dark', 2048),
            'favicon' => [
                Rule::when(
                    $this->hasFile('favicon'),
                    ['file', 'mimes:jpeg,jpg,png,webp,gif,svg,ico', 'max:512'],
                ),
            ],
            'hero_image' => $this->optionalImageRulesFor('hero_image', 4096),
            'promo_banner_image' => $this->optionalImageRulesFor('promo_banner_image', 4096),
            'default_og_image' => $this->optionalImageRulesFor('default_og_image', 4096),
            'logo_url' => ['nullable', 'string', 'max:1024'],
            'logo_dark_url' => ['nullable', 'string', 'max:1024'],
            'favicon_url' => ['nullable', 'string', 'max:1024'],

            'primary_color' => $hex,
            'secondary_color' => $hex,
            'primary_foreground_color' => $hex,

            'hero_title' => ['nullable', 'string', 'max:255'],
            'hero_subtitle' => ['nullable', 'string', 'max:2000'],
            'hero_badge' => ['nullable', 'string', 'max:64'],
            'hero_image_url' => ['nullable', 'string', 'max:1024'],
            'hero_cta_label' => ['nullable', 'string', 'max:64'],
            'hero_cta_url' => ['nullable', 'string', 'max:1024'],

            'promo_banner_image_url' => ['nullable', 'string', 'max:1024'],
            'promo_banner_link_url' => ['nullable', 'string', 'max:1024'],
            'promo_banner_title' => ['nullable', 'string', 'max:120'],

            'default_meta_title' => ['nullable', 'string', 'max:255'],
            'default_meta_description' => ['nullable', 'string', 'max:512'],
            'default_og_image_url' => ['nullable', 'string', 'max:1024'],
            'twitter_site' => ['nullable', 'string', 'max:64'],

            'ga4_enabled' => ['boolean'],
            'ga4_measurement_id' => ['nullable', 'string', 'max:64'],
            'gtm_enabled' => ['boolean'],
            'gtm_container_id' => [
                'nullable',
                'string',
                'max:32',
                Rule::when(
                    filled($this->input('gtm_container_id')),
                    ['regex:/^GTM-[A-Z0-9]+$/i']
                ),
            ],
            'meta_pixel_enabled' => ['boolean'],
            'meta_pixel_id' => ['nullable', 'string', 'max:32'],
            'tiktok_pixel_enabled' => ['boolean'],
            'tiktok_pixel_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalizeHex = function (?string $value): ?string {
            if ($value === null || trim($value) === '') {
                return null;
            }
            $v = ltrim(trim($value), '#');
            if (strlen($v) === 3) {
                $v = $v[0].$v[0].$v[1].$v[1].$v[2].$v[2];
            }

            return '#'.strtolower($v);
        };

        $merge = [
            'ga4_enabled' => filter_var($this->input('ga4_enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'gtm_enabled' => filter_var($this->input('gtm_enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'meta_pixel_enabled' => filter_var($this->input('meta_pixel_enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'tiktok_pixel_enabled' => filter_var($this->input('tiktok_pixel_enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'primary_color' => $normalizeHex($this->input('primary_color')),
            'secondary_color' => $normalizeHex($this->input('secondary_color')),
            'primary_foreground_color' => $normalizeHex($this->input('primary_foreground_color')),
        ];

        foreach (self::UPLOAD_KEYS as $key) {
            if ($this->input($key) === '' || $this->input($key) === 'null') {
                $merge[$key] = null;
            }
        }

        $this->merge($merge);
    }
}
