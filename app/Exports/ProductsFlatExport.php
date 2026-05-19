<?php

namespace App\Exports;

use App\Models\Product;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithHeadings;

final class ProductsFlatExport implements FromGenerator, WithHeadings
{
    public function __construct(
        private readonly Builder $productQuery,
    ) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'product_id',
            'brand_slug',
            'brand_id',
            'category_slug',
            'category_id',
            'size_chart_id',
            'name',
            'slug',
            'description',
            'meta_title',
            'meta_description',
            'canonical_url',
            'video_url',
            'video_poster',
            'fit_guidance',
            'gender',
            'shoe_type',
            'fit_notes',
            'size_info',
            'features',
            'product_is_active',
            'image_paths',
            'color_slug',
            'color_id',
            'sku',
            'price',
            'compare_at_price',
            'variant_is_active',
            'bargain_enabled',
            'bargain_min_price',
            'bargain_max_discount_percent',
            'size_label',
            'uk_size',
            'eu_size',
            'pk_size',
            'stock_qty',
            'low_stock_threshold',
        ];
    }

    public function generator(): Generator
    {
        $q = $this->productQuery->clone()
            ->with([
                'brand:id,name,slug',
                'category:id,name,slug',
                'images' => fn ($iq) => $iq->orderBy('sort_order'),
                'variants.color:id,name,slug',
                'variants.sizes' => fn ($sq) => $sq->orderBy('size_label'),
            ]);

        foreach ($q->lazyById(100, column: 'id') as $product) {
            /** @var Product $product */
            $imagePaths = $product->images->pluck('path')->filter()->implode('|');

            foreach ($product->variants as $variant) {
                foreach ($variant->sizes as $size) {
                    $features = $product->features;
                    $featuresCell = '';
                    if (is_array($features) && $features !== []) {
                        $featuresCell = implode('|', array_values(array_filter($features, static fn ($f) => is_string($f) && $f !== '')));
                    }

                    yield [
                        $product->id,
                        $product->brand?->slug,
                        $product->brand_id,
                        $product->category?->slug,
                        $product->category_id,
                        $product->size_chart_id,
                        $product->name,
                        $product->slug,
                        $product->description,
                        $product->meta_title,
                        $product->meta_description,
                        $product->canonical_url,
                        $product->video_url,
                        $product->video_poster,
                        $product->fit_guidance?->value,
                        $product->gender?->value,
                        $product->shoe_type?->value,
                        $product->fit_notes,
                        $product->size_info,
                        $featuresCell,
                        $product->is_active ? 1 : 0,
                        $imagePaths,
                        $variant->color?->slug,
                        $variant->color_id,
                        $variant->sku,
                        (float) $variant->price,
                        $variant->compare_at_price !== null ? (float) $variant->compare_at_price : '',
                        $variant->is_active ? 1 : 0,
                        $variant->bargain_enabled ? 1 : 0,
                        $variant->bargain_min_price !== null ? (float) $variant->bargain_min_price : '',
                        $variant->bargain_max_discount_percent !== null ? (float) $variant->bargain_max_discount_percent : '',
                        $size->size_label,
                        $size->uk_size,
                        $size->eu_size ?? '',
                        $size->pk_size ?? '',
                        (int) $size->stock_qty,
                        (int) $size->low_stock_threshold,
                    ];
                }
            }
        }
    }
}
