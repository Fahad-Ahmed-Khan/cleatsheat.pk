<?php

namespace App\Domain\Catalog;

use App\Models\Product;
use Illuminate\Support\Str;

final class ProductSearchIndexBuilder
{
    public function buildForProduct(Product $product): string
    {
        $product->loadMissing(['brand', 'category', 'variants.color', 'variants.sizes']);

        $parts = [
            $product->name,
            $product->slug,
            strip_tags((string) ($product->description ?? '')),
            $product->meta_title,
            $product->gender instanceof \BackedEnum ? $product->gender->value : (string) ($product->gender ?? ''),
            $product->shoe_type instanceof \BackedEnum ? $product->shoe_type->value : (string) ($product->shoe_type ?? ''),
        ];

        foreach ((array) ($product->features ?? []) as $feature) {
            if (is_string($feature) && $feature !== '') {
                $parts[] = $feature;
            }
        }

        if ($product->brand?->name) {
            $parts[] = $product->brand->name;
        }

        if ($product->category?->name) {
            $parts[] = $product->category->name;
        }

        foreach ($product->variants as $variant) {
            if (filled($variant->sku)) {
                $parts[] = $variant->sku;
            }
            if ($variant->color?->name) {
                $parts[] = $variant->color->name;
            }
            foreach ($variant->sizes as $size) {
                if (filled($size->size_label)) {
                    $parts[] = $size->size_label;
                }
                if (filled($size->uk_size)) {
                    $parts[] = $size->uk_size;
                }
            }
        }

        $normalized = implode(' ', array_filter(array_map(
            static fn (mixed $v): string => Str::squish((string) $v),
            $parts
        ), static fn (string $v): bool => $v !== ''));

        return mb_strtolower($normalized, 'UTF-8');
    }

    public function rebuildProduct(int $productId): void
    {
        $product = Product::query()
            ->with(['brand', 'category', 'variants.color', 'variants.sizes'])
            ->find($productId);

        if ($product === null) {
            return;
        }

        $text = $this->buildForProduct($product);

        Product::withoutEvents(function () use ($productId, $text): void {
            Product::query()
                ->whereKey($productId)
                ->update(['search_text' => $text]);
        });
    }
}
