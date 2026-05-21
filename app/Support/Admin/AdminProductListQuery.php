<?php

namespace App\Support\Admin;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class AdminProductListQuery
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(private array $filters) {}

    public static function fromRequest(Request $request): self
    {
        $perPage = (int) $request->input('per_page', 20);

        return new self([
            'search' => trim((string) $request->input('search', '')),
            'brand_id' => $request->input('brand_id'),
            'category_id' => $request->input('category_id'),
            'status' => $request->input('status'),
            'stock' => $request->input('stock'),
            'color_id' => $request->input('color_id'),
            'size' => $request->input('size'),
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'per_page' => $perPage <= 0 ? 20 : ($perPage > 100 ? 100 : $perPage),
        ]);
    }

    /**
     * Paginated admin list (matches existing product index behaviour).
     */
    public function forIndex(): Builder
    {
        $q = $this->baseWithStats();
        $this->applyFilters($q);

        return $q->latest();
    }

    /**
     * Ordered by id for stable export / streaming.
     */
    public function forExport(): Builder
    {
        $q = Product::query();
        $this->applyFilters($q);

        return $q->orderBy('products.id');
    }

    private function baseWithStats(): Builder
    {
        return Product::query()
            ->with(['brand:id,name', 'category:id,name'])
            ->withCount(['variants as variants_count'])
            ->withMin('variants as min_price', 'price')
            ->withMax('variants as max_price', 'price')
            ->addSelect([
                'primary_sku' => ProductVariant::query()
                    ->select('sku')
                    ->whereColumn('product_id', 'products.id')
                    ->orderBy('id')
                    ->limit(1),
                'sizes_count' => ProductVariant::query()
                    ->selectRaw('COUNT(variant_sizes.id)')
                    ->join('variant_sizes', 'variant_sizes.product_variant_id', '=', 'product_variants.id')
                    ->whereColumn('product_variants.product_id', 'products.id'),
                'stock_total' => ProductVariant::query()
                    ->selectRaw('COALESCE(SUM(variant_sizes.stock_qty), 0)')
                    ->join('variant_sizes', 'variant_sizes.product_variant_id', '=', 'product_variants.id')
                    ->whereColumn('product_variants.product_id', 'products.id'),
                'thumbnail_path' => ProductImage::query()
                    ->select('path')
                    ->whereColumn('product_id', 'products.id')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->limit(1),
            ]);
    }

    private function applyFilters(Builder $q): void
    {
        $search = (string) ($this->filters['search'] ?? '');
        $brandId = $this->filters['brand_id'] ?? null;
        $categoryId = $this->filters['category_id'] ?? null;
        $status = $this->filters['status'] ?? null;
        $stock = $this->filters['stock'] ?? null;
        $colorId = $this->filters['color_id'] ?? null;
        $sizeLabel = $this->filters['size'] ?? null;
        $priceMin = $this->filters['price_min'] ?? null;
        $priceMax = $this->filters['price_max'] ?? null;

        $q->when($search !== '', function ($query) use ($search) {
            $query->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('variants', fn ($vq) => $vq->where('sku', 'like', "%{$search}%"))
                    ->orWhereHas('category', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        })
            ->when($brandId !== null && $brandId !== '', fn ($query) => $query->where('brand_id', $brandId))
            ->when($categoryId !== null && $categoryId !== '', fn ($query) => $query->where('category_id', $categoryId))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($colorId !== null && $colorId !== '', fn ($query) => $query->whereHas('variants', fn ($vq) => $vq->where('color_id', $colorId)))
            ->when($sizeLabel !== null && $sizeLabel !== '', fn ($query) => $query->whereHas('variants.sizes', fn ($sq) => $sq->where('size_label', $sizeLabel)))
            ->when($priceMin !== null && $priceMin !== '', fn ($query) => $query->whereHas('variants', fn ($vq) => $vq->where('price', '>=', $priceMin)))
            ->when($priceMax !== null && $priceMax !== '', fn ($query) => $query->whereHas('variants', fn ($vq) => $vq->where('price', '<=', $priceMax)))
            ->when($stock === 'in', function ($query) {
                $query->whereExists(function ($sq) {
                    $sq->selectRaw('1')
                        ->from('product_variants')
                        ->join('variant_sizes', 'variant_sizes.product_variant_id', '=', 'product_variants.id')
                        ->whereColumn('product_variants.product_id', 'products.id')
                        ->where('variant_sizes.stock_qty', '>', 0);
                });
            })
            ->when($stock === 'out', function ($query) {
                $query->whereNotExists(function ($sq) {
                    $sq->selectRaw('1')
                        ->from('product_variants')
                        ->join('variant_sizes', 'variant_sizes.product_variant_id', '=', 'product_variants.id')
                        ->whereColumn('product_variants.product_id', 'products.id')
                        ->where('variant_sizes.stock_qty', '>', 0);
                });
            });
    }

    /**
     * @return array{search: string, brand_id: mixed, category_id: mixed, status: mixed, stock: mixed, color_id: mixed, size: mixed, price_min: mixed, price_max: mixed, per_page: int}
     */
    public function filtersForInertia(): array
    {
        $f = $this->filters;

        return [
            'search' => $f['search'] ?? '',
            'brand_id' => ($f['brand_id'] ?? '') === '' ? null : $f['brand_id'],
            'category_id' => ($f['category_id'] ?? '') === '' ? null : $f['category_id'],
            'status' => $f['status'] ?? null,
            'stock' => $f['stock'] ?? null,
            'color_id' => ($f['color_id'] ?? '') === '' ? null : $f['color_id'],
            'size' => $f['size'] ?? null,
            'price_min' => $f['price_min'] ?? null,
            'price_max' => $f['price_max'] ?? null,
            'per_page' => (int) ($f['per_page'] ?? 20),
        ];
    }
}
