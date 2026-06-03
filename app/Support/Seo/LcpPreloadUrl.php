<?php

namespace App\Support\Seo;

/**
 * Resolve the LCP image URL from the initial Inertia page payload for server-rendered preload hints.
 */
final class LcpPreloadUrl
{
    /**
     * @param  array<string, mixed>  $page
     */
    public static function fromInertiaPage(array $page): ?string
    {
        $component = (string) ($page['component'] ?? '');
        $props = is_array($page['props'] ?? null) ? $page['props'] : [];

        return match ($component) {
            'Store/Home' => self::stringOrNull($props['hero']['image_url'] ?? null),
            'Store/Product' => self::firstProductImage($props['product'] ?? null),
            'Store/Category', 'Store/Shop' => self::firstListingImage($props),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private static function firstListingImage(array $props): ?string
    {
        foreach (['products', 'featured'] as $key) {
            $list = $props[$key] ?? null;
            if (! is_array($list) || $list === []) {
                continue;
            }
            $first = $list[0] ?? null;
            if (! is_array($first)) {
                continue;
            }
            $images = $first['images'] ?? null;
            if (! is_array($images) || $images === []) {
                continue;
            }

            return self::stringOrNull($images[0]['path'] ?? null);
        }

        return null;
    }

    /**
     * @param  mixed  $product
     */
    private static function firstProductImage(mixed $product): ?string
    {
        if (! is_array($product)) {
            return null;
        }
        $images = $product['images'] ?? null;
        if (! is_array($images) || $images === []) {
            return null;
        }

        return self::stringOrNull($images[0]['path'] ?? null);
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
