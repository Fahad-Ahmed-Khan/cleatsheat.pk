<?php

namespace App\Support\Store;

use App\Models\Product;

final class ProductCardMeta
{
    /**
     * @return array{
     *     card_surface_label: ?string,
     *     card_condition_label: string,
     *     card_condition_kind: 'new'|'used',
     *     card_condition_badge: string,
     *     card_authenticity_label: string,
     *     quick_add: ?array{variant_id: int, size_label: string}
     * }
     */
    public static function forProduct(Product $product): array
    {
        $features = $product->features ?? [];
        $conditionLabel = 'Inspected pre-owned';
        $authenticity = 'Verified original';

        foreach ($features as $feature) {
            $f = strtolower((string) $feature);
            if (str_contains($f, 'condition') || str_contains($f, 'second-hand') || str_contains($f, 'used') || str_contains($f, 'pre-owned') || str_contains($f, 'pre-loved')) {
                $conditionLabel = (string) $feature;
            }
            if (str_contains($f, 'authentic') || str_contains($f, 'original')) {
                $authenticity = (string) $feature;
            }
        }

        $surface = self::surfaceLabel($product);
        $condition = self::resolveCondition($product, $features, $conditionLabel);

        return [
            'card_surface_label' => $surface,
            'card_condition_label' => $conditionLabel,
            'card_condition_kind' => $condition['kind'],
            'card_condition_badge' => $condition['badge'],
            'card_authenticity_label' => $authenticity,
            'quick_add' => self::quickAddPayload($product),
        ];
    }

    /**
     * @param  list<string>  $features
     * @return array{kind: 'new'|'used', badge: string}
     */
    private static function resolveCondition(Product $product, array $features, string $conditionLabel): array
    {
        $haystack = strtolower(implode(' ', [
            (string) $product->description,
            ...array_map('strval', $features),
            (string) ($product->category?->slug ?? ''),
        ]));

        $rating = self::parseConditionRating($haystack);
        $isUsed = self::hasUsedSignals($haystack);
        $isNew = self::hasNewSignals($haystack, (string) ($product->category?->slug ?? ''));

        if ($isNew && ! $isUsed) {
            return ['kind' => 'new', 'badge' => 'Brand New'];
        }

        if ($rating !== null) {
            return ['kind' => 'used', 'badge' => $rating.'/10 Condition'];
        }

        if ($isUsed) {
            return ['kind' => 'used', 'badge' => self::usedBadgeFromLabel($conditionLabel)];
        }

        return ['kind' => 'used', 'badge' => 'Pre-Loved'];
    }

    private static function hasUsedSignals(string $haystack): bool
    {
        foreach (['used', 'pre-owned', 'pre-loved', 'second-hand', 'inspected pre-owned', 'pre-owned'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function hasNewSignals(string $haystack, string $categorySlug): bool
    {
        if ($categorySlug === 'new-arrivals') {
            return true;
        }

        return str_contains($haystack, 'brand new')
            || str_contains($haystack, 'new in box')
            || preg_match('/\bnew\b(?!\s*arrival)/', $haystack) === 1 && ! self::hasUsedSignals($haystack);
    }

    private static function parseConditionRating(string $haystack): ?string
    {
        if (preg_match('/condition:\s*\*?\*?(\d+(?:\.\d+)?)\s*\/\s*10/i', $haystack, $m)) {
            return $m[1];
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*\/\s*10/i', $haystack, $m)) {
            return $m[1];
        }

        return null;
    }

    private static function usedBadgeFromLabel(string $conditionLabel): string
    {
        $lower = strtolower($conditionLabel);
        if (str_contains($lower, 'pre-loved') || str_contains($lower, 'pre-owned') || str_contains($lower, 'second-hand')) {
            return 'Pre-Loved';
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*\/\s*10/i', $conditionLabel, $m)) {
            return $m[1].'/10 Condition';
        }

        return 'Pre-Loved';
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
