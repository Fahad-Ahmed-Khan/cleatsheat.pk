<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\VariantSize;
use App\Support\Storage\PublicAssetUrl;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductManagementService
{
    /**
     * @param  array<string, mixed>  $data  validated payload including variants & images
     */
    public function store(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::query()->create($this->productAttributes($data));

            $this->syncImages($product, $data['images'] ?? []);
            $this->syncVariants($product, $data['variants']);

            return $product->fresh(['brand', 'category', 'images', 'variants.sizes', 'variants.color']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $product->update($this->productAttributes($data));

            if (array_key_exists('images', $data)) {
                $this->syncImages($product, $data['images'] ?? []);
            }

            if (array_key_exists('variants', $data)) {
                $product->variants()->delete();
                $this->syncVariants($product, $data['variants']);
            }

            return $product->fresh(['brand', 'category', 'images', 'variants.sizes', 'variants.color']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function productAttributes(array $data): array
    {
        $attrs = collect($data)->only([
            'brand_id',
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
            'is_active',
        ])->all();

        // If an uploaded video file is provided, store it and use its public URL as video_url.
        $videoFile = $data['video_file'] ?? null;
        if ($videoFile instanceof UploadedFile) {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk('public');
            $stored = $disk->putFile('products/videos', $videoFile);
            $attrs['video_url'] = $stored;
        }

        // Treat empty strings as null for video fields so the column stays clean.
        foreach (['video_url', 'video_poster'] as $key) {
            if (array_key_exists($key, $attrs) && is_string($attrs[$key]) && trim($attrs[$key]) === '') {
                $attrs[$key] = null;
            }
        }

        return $attrs;
    }

    /**
     * @param  list<array{path?: string|null, file?: mixed, alt?: string|null, sort_order?: int}>  $images
     */
    private function syncImages(Product $product, array $images): void
    {
        $product->images()->delete();

        foreach ($images as $i => $row) {
            $path = $row['path'] ?? null;
            $file = $row['file'] ?? null;

            if ($file instanceof UploadedFile) {
                /** @var FilesystemAdapter $disk */
                $disk = Storage::disk('public');
                $stored = $disk->putFile('products', $file);
                $path = $stored;
            }

            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            $path = PublicAssetUrl::normalizeForStorage($path);

            ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => $path,
                'alt' => $row['alt'] ?? null,
                'sort_order' => $row['sort_order'] ?? $i,
            ]);
        }
    }

    /**
     * @param  list<array{color_id: int, sku: string, price: mixed, compare_at_price?: mixed, is_active?: bool, sizes: list<array<string, mixed>>}>  $variants
     */
    private function syncVariants(Product $product, array $variants): void
    {
        foreach ($variants as $variantRow) {
            $variant = ProductVariant::query()->create([
                'product_id' => $product->id,
                'color_id' => $variantRow['color_id'],
                'sku' => $variantRow['sku'],
                'price' => $variantRow['price'],
                'compare_at_price' => $variantRow['compare_at_price'] ?? null,
                'bargain_enabled' => $variantRow['bargain_enabled'] ?? false,
                'bargain_min_price' => $variantRow['bargain_min_price'] ?? null,
                'bargain_max_discount_percent' => $variantRow['bargain_max_discount_percent'] ?? null,
                'is_active' => $variantRow['is_active'] ?? true,
            ]);

            foreach ($variantRow['sizes'] as $sizeRow) {
                VariantSize::query()->create([
                    'product_variant_id' => $variant->id,
                    'size_label' => $sizeRow['size_label'],
                    'uk_size' => $sizeRow['uk_size'] ?? $sizeRow['size_label'],
                    'eu_size' => $sizeRow['eu_size'] ?? null,
                    'pk_size' => $sizeRow['pk_size'] ?? null,
                    'stock_qty' => $sizeRow['stock_qty'],
                    'low_stock_threshold' => $sizeRow['low_stock_threshold'] ?? 0,
                ]);
            }
        }
    }
}
