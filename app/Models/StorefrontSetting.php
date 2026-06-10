<?php

namespace App\Models;

use App\Support\Images\ResponsiveImageGenerator;
use App\Support\Storage\PublicAssetUrl;
use Illuminate\Database\Eloquent\Model;

class StorefrontSetting extends Model
{
    /** @var list<string> */
    public const PUBLIC_ASSET_COLUMNS = [
        'logo_url',
        'logo_dark_url',
        'favicon_url',
        'hero_image_url',
        'promo_banner_image_url',
        'default_og_image_url',
    ];

    protected $fillable = [
        'site_name',
        'logo_url',
        'logo_dark_url',
        'favicon_url',
        'primary_color',
        'secondary_color',
        'primary_foreground_color',
        'hero_title',
        'hero_subtitle',
        'hero_badge',
        'hero_image_url',
        'hero_image_width',
        'hero_image_height',
        'hero_image_variants',
        'hero_cta_label',
        'hero_cta_url',
        'promo_banner_image_url',
        'promo_banner_link_url',
        'promo_banner_title',
        'default_meta_title',
        'default_meta_description',
        'default_og_image_url',
        'twitter_site',
        'ga4_enabled',
        'ga4_measurement_id',
        'gtm_enabled',
        'gtm_container_id',
        'meta_pixel_enabled',
        'meta_pixel_id',
        'tiktok_pixel_enabled',
        'tiktok_pixel_id',
        'home_testimonials',
        'home_social_posts',
        'home_seo_html',
        'instagram_url',
        'tiktok_url',
        'newsletter_enabled',
    ];

    protected function casts(): array
    {
        return [
            'ga4_enabled' => 'boolean',
            'gtm_enabled' => 'boolean',
            'meta_pixel_enabled' => 'boolean',
            'tiktok_pixel_enabled' => 'boolean',
            'home_testimonials' => 'array',
            'home_social_posts' => 'array',
            'newsletter_enabled' => 'boolean',
            'hero_image_variants' => 'array',
        ];
    }

    public static function resolveAssetUrl(?string $stored): ?string
    {
        return PublicAssetUrl::resolve($stored);
    }

    public static function normalizeStoredAssetPath(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return PublicAssetUrl::normalizeForStorage($value) ?? $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeStoredAssetPaths(array $data): array
    {
        foreach (self::PUBLIC_ASSET_COLUMNS as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = self::normalizeStoredAssetPath(
                    is_string($data[$key] ?? null) ? $data[$key] : null,
                );
            }
        }

        return $data;
    }

    /**
     * Admin form + API payloads need fully qualified URLs for previews.
     *
     * @return array<string, mixed>
     */
    public function toAdminSettingsPayload(): array
    {
        $payload = $this->only([
            'site_name',
            'logo_url',
            'logo_dark_url',
            'favicon_url',
            'primary_color',
            'secondary_color',
            'primary_foreground_color',
            'hero_title',
            'hero_subtitle',
            'hero_badge',
            'hero_image_url',
            'hero_cta_label',
            'hero_cta_url',
            'promo_banner_image_url',
            'promo_banner_link_url',
            'promo_banner_title',
            'default_meta_title',
            'default_meta_description',
            'default_og_image_url',
            'twitter_site',
            'ga4_enabled',
            'ga4_measurement_id',
            'gtm_enabled',
            'gtm_container_id',
            'meta_pixel_enabled',
            'meta_pixel_id',
            'tiktok_pixel_enabled',
            'tiktok_pixel_id',
        ]);

        foreach (self::PUBLIC_ASSET_COLUMNS as $key) {
            if (! empty($payload[$key])) {
                $payload[$key] = PublicAssetUrl::resolve($payload[$key]);
            }
        }

        return $payload;
    }

    public static function current(): self
    {
        $row = static::query()->first();
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'primary_color' => '#dfff00',
            'secondary_color' => '#576500',
            'primary_foreground_color' => '#191e00',
            'hero_title' => config('store.hero_title'),
            'hero_subtitle' => config('store.hero_subtitle'),
            'hero_badge' => config('store.hero_badge'),
            'hero_image_url' => config('store.hero_image_url'),
        ]);
    }

    /**
     * @return array{primary: string, secondary: string, primaryForeground: string}|null
     */
    public static function hexToRgbTriple(?string $hex): ?string
    {
        if (! is_string($hex) || $hex === '') {
            return null;
        }

        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return null;
        }

        return implode(' ', [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ]);
    }

    /**
     * Branding + theme tokens for the storefront (shared on every Inertia page).
     *
     * @return array<string, mixed>
     */
    public function toBrandingPayload(): array
    {
        $primary = self::hexToRgbTriple($this->primary_color) ?? '223 255 0';
        $secondary = self::hexToRgbTriple($this->secondary_color) ?? '87 101 0';
        $primaryFg = self::hexToRgbTriple($this->primary_foreground_color) ?? '25 30 0';

        return [
            'site_name' => $this->site_name,
            'logo_url' => PublicAssetUrl::resolve($this->logo_url),
            'logo_dark_url' => PublicAssetUrl::resolve($this->logo_dark_url),
            'favicon_url' => PublicAssetUrl::resolve($this->favicon_url),
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'primary_foreground_color' => $this->primary_foreground_color,
            'theme' => [
                'primary' => $primary,
                'secondary' => $secondary,
                'primaryForeground' => $primaryFg,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toHeroPayload(): array
    {
        $usingStoredImage = filled($this->hero_image_url);

        $fallbackHero = config('store.hero_image_url');

        return [
            'title' => $this->hero_title ?: config('store.hero_title'),
            'subtitle' => $this->hero_subtitle ?: config('store.hero_subtitle'),
            'badge' => $this->hero_badge ?: config('store.hero_badge'),
            'image_url' => PublicAssetUrl::resolve($this->hero_image_url)
                ?: (is_string($fallbackHero) ? PublicAssetUrl::resolve($fallbackHero) ?? $fallbackHero : null),
            'image_srcset' => $usingStoredImage ? self::buildSrcset($this->hero_image_variants) : null,
            'image_width' => $usingStoredImage ? $this->hero_image_width : null,
            'image_height' => $usingStoredImage ? $this->hero_image_height : null,
            'cta_label' => $this->hero_cta_label,
            'cta_url' => $this->hero_cta_url,
        ];
    }

    /**
     * Build a responsive srcset string from stored WebP variant metadata.
     *
     * @param  mixed  $variants  list of ['w' => int, 'path' => string]
     */
    public static function buildSrcset($variants): ?string
    {
        return ResponsiveImageGenerator::buildSrcset($variants);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPromoBannerPayload(): array
    {
        return [
            'image_url' => PublicAssetUrl::resolve($this->promo_banner_image_url),
            'link_url' => $this->promo_banner_link_url,
            'title' => $this->promo_banner_title,
        ];
    }

    /**
     * Analytics / pixels (same shape as legacy marketing_settings public payload + GTM).
     *
     * @return array<string, mixed>
     */
    /**
     * Homepage trust, social, SEO blocks for Inertia.
     *
     * @return array<string, mixed>
     */
    public function toHomeContentPayload(): array
    {
        return [
            'testimonials' => $this->home_testimonials ?? self::defaultTestimonials(),
            'social' => [
                'instagram_url' => $this->instagram_url,
                'tiktok_url' => $this->tiktok_url,
                'posts' => $this->home_social_posts ?? self::defaultSocialPosts(),
            ],
            'seo_html' => $this->home_seo_html,
            'newsletter_enabled' => $this->newsletter_enabled ?? true,
        ];
    }

    /**
     * @return list<array{name: string, city: string, quote: string, rating: int}>
     */
    public static function defaultTestimonials(): array
    {
        return [
            [
                'name' => 'Hassan R.',
                'city' => 'Lahore',
                'quote' => 'FG Mercurials arrived exactly as listed — clean studs, true UK 9. COD was smooth.',
                'rating' => 5,
            ],
            [
                'name' => 'Ali K.',
                'city' => 'Karachi',
                'quote' => 'WhatsApp sizing help nailed it. My SG pair handles Karachi rain pitches perfectly.',
                'rating' => 5,
            ],
            [
                'name' => 'Usman T.',
                'city' => 'Islamabad',
                'quote' => 'Authentic used boots at fair PKR — better than hunting in Sunday bazaars.',
                'rating' => 5,
            ],
        ];
    }

    /**
     * @return list<array{platform: string, image_url: string, caption: string, url: string}>
     */
    public static function defaultSocialPosts(): array
    {
        return [
            [
                'platform' => 'instagram',
                'image_url' => null,
                'caption' => 'Match-day fit checks',
                'url' => '#',
            ],
            [
                'platform' => 'instagram',
                'image_url' => null,
                'caption' => 'New FG drops',
                'url' => '#',
            ],
            [
                'platform' => 'tiktok',
                'image_url' => null,
                'caption' => 'Boot unboxing',
                'url' => '#',
            ],
            [
                'platform' => 'tiktok',
                'image_url' => null,
                'caption' => 'Surface guide',
                'url' => '#',
            ],
        ];
    }

    public function toAnalyticsPayload(): array
    {
        return [
            'ga4_enabled' => (bool) $this->ga4_enabled && filled($this->ga4_measurement_id),
            'ga4_measurement_id' => $this->ga4_measurement_id,
            'gtm_enabled' => (bool) $this->gtm_enabled && filled($this->gtm_container_id),
            'gtm_container_id' => $this->gtm_container_id,
            'meta_pixel_enabled' => (bool) $this->meta_pixel_enabled && filled($this->meta_pixel_id),
            'meta_pixel_id' => $this->meta_pixel_id,
            'tiktok_pixel_enabled' => (bool) $this->tiktok_pixel_enabled && filled($this->tiktok_pixel_id),
            'tiktok_pixel_id' => $this->tiktok_pixel_id,
        ];
    }
}
