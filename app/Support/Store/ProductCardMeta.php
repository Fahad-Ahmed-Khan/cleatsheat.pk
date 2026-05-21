<?php

namespace App\Support\Store;

use App\Models\Product;

final class ProductCardMeta
{
    /**
     * @return array{card_surface_label: ?string, card_condition_label: string, card_authenticity_label: string, quick_add: ?array{variant_id: int, size_label: string}}
     */
    public static function forProduct(Product $product): array
    {
        $features = $product->features ?? [];
        $condition = 'Inspected pre-owned';
        $authenticity = 'Verified original';

        foreach ($features as $feature) {
            $f = strtolower((string) $feature);
            if (str_contains($f, 'condition') || str_contains($f, 'second-hand') || str_contains($f, 'used') || str_contains($f, 'pre-owned')) {
                $condition = (string) $feature;
            }
            if (str_contains($f, 'authentic') || str_contains($f, 'original')) {
                $authenticity = (string) $feature;
            }
        }

        $surface = self::surfaceLabel($product);

        return [
            'card_surface_label' => $surface,
            'card_condition_label' => $condition,
            'card_authenticity_label' => $authenticity,
            'quick_add' => self::quickAddPayload($product),
        ];
    }

    public static function surfaceLabel(Product $product): ?string
    {
        $slug = strtolower((string) ($product->category?->slug ?? ''));
        $name = strtolower((string) ($product->category?->name ?? ''));

        $map = [
            'fg' => 'FG',
            'firm' => 'FG',
            'sg' => 'SG',
            'soft' => 'SG',
            'ag' => 'AG',
            'artificial' => 'AG',
            'turf' => 'Turf',
            'indoor' => 'Indoor',
            'astro' => 'Turf',
            'tf' => 'Turf',
        ];

        foreach ($map as $needle => $label) {
            if (str_contains($slug, $needle) || str_contains($name, $needle)) {
                return $label;
            }
        }

        if (preg_match('/\((FG|SG|AG|TF|IC)\)/i', (string) $product->name, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    /**
     * @return array{variant_id: int, size_label: string}|null
     */
    public static function quickAddPayload(Product $product): ?array
    {
        $variants = $product->variants->where('is_active', true)->values();
        if ($variants->count() !== 1) {
            return null;
        }

        $variant = $variants->first();
        $inStock = $variant->sizes->filter(fn ($s) => $s->stock_qty === null || $s->stock_qty > 0)->values();
        if ($inStock->count() !== 1) {
            return null;
        }

        $size = $inStock->first();

        return [
            'variant_id' => (int) $variant->id,
            'size_label' => (string) $size->size_label,
        ];
    }
}
