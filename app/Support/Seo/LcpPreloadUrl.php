<?php

namespace App\Support\Seo;

/**
 * Resolve the LCP image (URL + responsive srcset/sizes) from the initial Inertia
 * page payload so the Blade root can emit an accurate <link rel="preload"> hint.
 */
final class LcpPreloadUrl
{
    /** sizes hint for the full-bleed hero image. */
    private const SIZES_HERO = '100vw';

    /** sizes hint for the product gallery LCP image. */
    private const SIZES_PRODUCT = '(min-width: 1024px) 50vw, 100vw';

    /** sizes hint matching StoreProductCard grid cells. */
    private const SIZES_CARD = '(min-width: 1024px) 23vw, (min-width: 640px) 31vw, 48vw';

    /**
     * @param  array<string, mixed>  $page
     * @return array{href: string, srcset: ?string, sizes: ?string}|null
     */
    public static function fromInertiaPage(array $page): ?array
    {
        $component = (string) ($page['component'] ?? '');
        $props = is_array($page['props'] ?? null) ? $page['props'] : [];

        return match ($component) {
            'Store/Home' => self::heroImage($props['hero'] ?? null),
            'Store/Product' => self::firstProductImage($props['product'] ?? null),
            'Store/Category', 'Store/Shop' => self::firstListingImage($props),
            default => null,
        };
    }

    /**
     * @param  mixed  $hero
     * @return array{href: string, srcset: ?string, sizes: ?string}|null
     */
    private static function heroImage(mixed $hero): ?array
    {
        if (! is_array($hero)) {
            return null;
        }

        $href = self::stringOrNull($hero['image_url'] ?? null);
        if ($href === null) {
            return null;
        }

        return self::hint($href, self::stringOrNull($hero['image_srcset'] ?? null), self::SIZES_HERO);
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array{href: string, srcset: ?string, sizes: ?string}|null
     */
    private static function firstListingImage(array $props): ?array
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

            $href = self::stringOrNull($images[0]['path'] ?? null);
            if ($href === null) {
                continue;
            }

            return self::hint($href, self::stringOrNull($images[0]['srcset'] ?? null), self::SIZES_CARD);
        }

        return null;
    }

    /**
     * @param  mixed  $product
     * @return array{href: string, srcset: ?string, sizes: ?string}|null
     */
    private static function firstProductImage(mixed $product): ?array
    {
        if (! is_array($product)) {
            return null;
        }
        $images = $product['images'] ?? null;
        if (! is_array($images) || $images === []) {
            return null;
        }

        $href = self::stringOrNull($images[0]['path'] ?? null);
        if ($href === null) {
            return null;
        }

        return self::hint($href, self::stringOrNull($images[0]['srcset'] ?? null), self::SIZES_PRODUCT);
    }

    /**
     * @return array{href: string, srcset: ?string, sizes: ?string}
     */
    private static function hint(string $href, ?string $srcset, string $sizes): array
    {
        return [
            'href' => $href,
            'srcset' => $srcset,
            'sizes' => $srcset !== null ? $sizes : null,
        ];
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
