<?php

namespace App\Domain\Catalog;

use App\Enums\Gender;
use App\Enums\ShoeType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogQueryService
{
    /**
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                static fn (mixed $v): ?string => is_string($v) || is_numeric($v) ? trim((string) $v) : null,
                $value
            ), static fn (?string $v): bool => $v !== null && $v !== ''));
        }

        if (is_string($value) || is_numeric($value)) {
            $s = trim((string) $value);
            return $s === '' ? [] : [$s];
        }

        return [];
    }

    /** @return Collection<int, Product> */
    public function featuredProducts(int $limit = 8): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->with(['brand', 'images', 'variants.color'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, Product> */
    public function newArrivals(int $limit = 8): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->with(['brand', 'images', 'variants.color'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Best sellers by units sold (order_items). Falls back to newest when no sales data.
     *
     * @return Collection<int, Product>
     */
    public function bestSellingProducts(int $limit = 8): Collection
    {
        $rows = DB::table('order_items')
            ->join('product_variants', 'order_items.product_variant_id', '=', 'product_variants.id')
            ->selectRaw('product_variants.product_id, SUM(order_items.quantity) as sold')
            ->groupBy('product_variants.product_id')
            ->orderByDesc('sold')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return $this->newArrivals($limit);
        }

        $ids = $rows->pluck('product_id');
        $products = Product::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->with(['brand', 'images', 'variants.color'])
            ->get()
            ->keyBy('id');

        $ordered = $ids
            ->map(fn ($id) => $products->get($id))
            ->filter()
            ->values()
            ->all();

        return new Collection($ordered);
    }

    /**
     * “Trending” — offset slice so the shelf differs from new arrivals when enough SKUs exist.
     *
     * @return Collection<int, Product>
     */
    public function trendingProducts(int $limit = 8): Collection
    {
        $slice = Product::query()
            ->where('is_active', true)
            ->with(['brand', 'images', 'variants.color'])
            ->latest()
            ->skip(8)
            ->take($limit)
            ->get();

        if ($slice->isNotEmpty()) {
            return $slice;
        }

        return $this->newArrivals($limit);
    }

    /** @return Collection<int, Category> */
    public function rootCategories(): Collection
    {
        return Category::query()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with('children')
            ->get();
    }

    public function categoryBySlug(string $slug): Category
    {
        return Category::query()->where('slug', $slug)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function filterOptionsForCategory(Category $category): array
    {
        $base = Product::query()
            ->where('category_id', $category->id)
            ->where('is_active', true);

        $brandIds = (clone $base)->pluck('brand_id')->unique()->filter()->values();
        $brands = $brandIds->isEmpty()
            ? collect()
            : Brand::query()->whereIn('id', $brandIds)->orderBy('name')->get(['id', 'name', 'slug']);

        $colorIds = ProductVariant::query()
            ->whereHas('product', fn (Builder $q) => $q->where('category_id', $category->id)->where('is_active', true))
            ->pluck('color_id')
            ->unique()
            ->filter()
            ->values();

        $colors = $colorIds->isEmpty()
            ? collect()
            : Color::query()->whereIn('id', $colorIds)->orderBy('name')->get(['id', 'name', 'slug', 'hex']);

        $priceAgg = ProductVariant::query()
            ->whereHas('product', fn (Builder $q) => $q->where('category_id', $category->id)->where('is_active', true))
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        $sizes = DB::table('variant_sizes')
            ->join('product_variants', 'variant_sizes.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->where('products.category_id', $category->id)
            ->where('products.is_active', true)
            ->distinct()
            ->orderBy('variant_sizes.size_label')
            ->pluck('variant_sizes.size_label')
            ->values()
            ->all();

        $genders = (clone $base)
            ->select('gender')
            ->distinct()
            ->pluck('gender')
            ->map(fn ($g) => $g instanceof Gender ? $g->value : (string) $g)
            ->filter()
            ->values()
            ->all();

        return [
            'brands' => $brands,
            'colors' => $colors,
            'sizes' => $sizes,
            'genders' => $genders,
            'price_min' => $priceAgg?->min_price !== null ? (float) $priceAgg->min_price : 0,
            'price_max' => $priceAgg?->max_price !== null ? (float) $priceAgg->max_price : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginatedFilteredProductsForCategory(Category $category, array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $query = Product::query()
            ->where('category_id', $category->id)
            ->where('is_active', true)
            ->with(['brand', 'images', 'variants.color', 'variants.sizes']);

        $brandIds = array_values(array_filter(array_map('intval', (array) ($filters['brand_ids'] ?? []))));
        if ($brandIds !== []) {
            $query->whereIn('brand_id', $brandIds);
        }

        $colorIds = array_values(array_filter(array_map('intval', (array) ($filters['color_ids'] ?? []))));
        if ($colorIds !== []) {
            $query->whereHas('variants', fn (Builder $vq) => $vq->whereIn('color_id', $colorIds));
        }

        $gender = $filters['gender'] ?? null;
        if (is_string($gender) && $gender !== '') {
            $query->where('gender', $gender);
        }

        $type = $filters['type'] ?? null;
        $typeList = $this->normalizeStringList($type);
        if ($typeList !== []) {
            $allowed = array_map(static fn (ShoeType $c): string => $c->value, ShoeType::cases());
            $typeList = array_values(array_intersect($typeList, $allowed));
            if ($typeList !== []) {
                $query->whereIn('shoe_type', $typeList);
            }
        }

        $priceMin = $filters['price_min'] ?? null;
        if ($priceMin !== null && $priceMin !== '') {
            $query->whereHas('variants', fn (Builder $vq) => $vq->where('price', '>=', (float) $priceMin));
        }

        $priceMax = $filters['price_max'] ?? null;
        if ($priceMax !== null && $priceMax !== '') {
            $query->whereHas('variants', fn (Builder $vq) => $vq->where('price', '<=', (float) $priceMax));
        }

        $sizeLabel = $filters['size'] ?? null;
        if (is_string($sizeLabel) && $sizeLabel !== '') {
            $query->whereHas('variants.sizes', fn (Builder $sq) => $sq
                ->where('size_label', $sizeLabel)
                ->where('stock_qty', '>', 0));
        }

        $sizeUk = $filters['size_uk'] ?? null;
        $sizeUkList = $this->normalizeStringList($sizeUk);
        if ($sizeUkList !== []) {
            $query->whereHas('variants.sizes', fn (Builder $sq) => $sq
                ->whereIn('uk_size', $sizeUkList)
                ->where('stock_qty', '>', 0));
        }

        $availability = $filters['availability'] ?? null;
        if ($availability === 'in_stock') {
            $query->whereHas('variants.sizes', fn (Builder $sq) => $sq->where('stock_qty', '>', 0));
        }

        return $query
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @deprecated Use paginatedFilteredProductsForCategory with empty filters.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginatedProductsForCategory(Category $category, int $perPage = 12): LengthAwarePaginator
    {
        return $this->paginatedFilteredProductsForCategory($category, [], $perPage);
    }

    /**
     * Filter options aggregated across the ENTIRE active catalog (no category constraint).
     * Used by the dedicated /shop listing.
     *
     * @return array<string, mixed>
     */
    public function filterOptionsAll(): array
    {
        $base = Product::query()->where('is_active', true);

        $brandIds = (clone $base)->pluck('brand_id')->unique()->filter()->values();
        $brands = $brandIds->isEmpty()
            ? collect()
            : Brand::query()->whereIn('id', $brandIds)->orderBy('name')->get(['id', 'name', 'slug']);

        $colorIds = ProductVariant::query()
            ->whereHas('product', fn (Builder $q) => $q->where('is_active', true))
            ->pluck('color_id')
            ->unique()
            ->filter()
            ->values();

        $colors = $colorIds->isEmpty()
            ? collect()
            : Color::query()->whereIn('id', $colorIds)->orderBy('name')->get(['id', 'name', 'slug', 'hex']);

        $priceAgg = ProductVariant::query()
            ->whereHas('product', fn (Builder $q) => $q->where('is_active', true))
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        $sizes = DB::table('variant_sizes')
            ->join('product_variants', 'variant_sizes.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->where('products.is_active', true)
            ->distinct()
            ->orderBy('variant_sizes.size_label')
            ->pluck('variant_sizes.size_label')
            ->values()
            ->all();

        $genders = (clone $base)
            ->select('gender')
            ->distinct()
            ->pluck('gender')
            ->map(fn ($g) => $g instanceof Gender ? $g->value : (string) $g)
            ->filter()
            ->values()
            ->all();

        $categories = Category::query()
            ->whereIn('id', (clone $base)->pluck('category_id')->unique()->filter()->values())
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return [
            'brands' => $brands,
            'colors' => $colors,
            'sizes' => $sizes,
            'genders' => $genders,
            'categories' => $categories,
            'price_min' => $priceAgg?->min_price !== null ? (float) $priceAgg->min_price : 0,
            'price_max' => $priceAgg?->max_price !== null ? (float) $priceAgg->max_price : 0,
        ];
    }

    /**
     * Paginated products across the entire active catalog with optional filters.
     * Adds an optional `category_ids` filter on top of the standard filter set.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginatedFilteredAllProducts(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $query = Product::query()
            ->where('is_active', true)
            ->with(['brand', 'images', 'variants.color', 'variants.sizes']);

        $categoryIds = array_values(array_filter(array_map('intval', (array) ($filters['category_ids'] ?? []))));
        if ($categoryIds !== []) {
            $query->whereIn('category_id', $categoryIds);
        }

        $brandIds = array_values(array_filter(array_map('intval', (array) ($filters['brand_ids'] ?? []))));
        if ($brandIds !== []) {
            $query->whereIn('brand_id', $brandIds);
        }

        $colorIds = array_values(array_filter(array_map('intval', (array) ($filters['color_ids'] ?? []))));
        if ($colorIds !== []) {
            $query->whereHas('variants', fn (Builder $vq) => $vq->whereIn('color_id', $colorIds));
        }

        $gender = $filters['gender'] ?? null;
        if (is_string($gender) && $gender !== '') {
            $query->where('gender', $gender);
        }

        $type = $filters['type'] ?? null;
        $typeList = $this->normalizeStringList($type);
        if ($typeList !== []) {
            $allowed = array_map(static fn (ShoeType $c): string => $c->value, ShoeType::cases());
            $typeList = array_values(array_intersect($typeList, $allowed));
            if ($typeList !== []) {
                $query->whereIn('shoe_type', $typeList);
            }
        }

        $priceMin = $filters['price_min'] ?? null;
        if ($priceMin !== null && $priceMin !== '') {
            $query->whereHas('variants', fn (Builder $vq) => $vq->where('price', '>=', (float) $priceMin));
        }

        $priceMax = $filters['price_max'] ?? null;
        if ($priceMax !== null && $priceMax !== '') {
            $query->whereHas('variants', fn (Builder $vq) => $vq->where('price', '<=', (float) $priceMax));
        }

        $sizeLabel = $filters['size'] ?? null;
        if (is_string($sizeLabel) && $sizeLabel !== '') {
            $query->whereHas('variants.sizes', fn (Builder $sq) => $sq
                ->where('size_label', $sizeLabel)
                ->where('stock_qty', '>', 0));
        }

        $sizeUk = $filters['size_uk'] ?? null;
        $sizeUkList = $this->normalizeStringList($sizeUk);
        if ($sizeUkList !== []) {
            $query->whereHas('variants.sizes', fn (Builder $sq) => $sq
                ->whereIn('uk_size', $sizeUkList)
                ->where('stock_qty', '>', 0));
        }

        $availability = $filters['availability'] ?? null;
        if ($availability === 'in_stock') {
            $query->whereHas('variants.sizes', fn (Builder $sq) => $sq->where('stock_qty', '>', 0));
        }

        $sort = $filters['sort'] ?? null;
        if ($sort === 'price_asc') {
            $query->orderBy(
                ProductVariant::query()->select('price')->whereColumn('product_id', 'products.id')->orderBy('price')->limit(1),
                'asc'
            );
        } elseif ($sort === 'price_desc') {
            $query->orderBy(
                ProductVariant::query()->select('price')->whereColumn('product_id', 'products.id')->orderBy('price')->limit(1),
                'desc'
            );
        } else {
            $query->latest();
        }

        return $query
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Extends parseProductListFilters with optional `category_ids` and `sort`,
     * used by the dedicated /shop listing.
     *
     * @return array<string, mixed>
     */
    public function parseShopFilters(Request $request): array
    {
        $base = $this->parseProductListFilters($request);
        $base['category_ids'] = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) $request->input('category_ids', [])
        ))));
        $sort = $request->input('sort');
        $base['sort'] = in_array($sort, ['price_asc', 'price_desc', 'newest'], true) ? $sort : null;

        return $base;
    }

    /**
     * Query-string filters for category product listings (storefront + API).
     *
     * @return array<string, mixed>
     */
    public function parseProductListFilters(Request $request): array
    {
        return [
            'brand_ids' => array_values(array_unique(array_filter(array_map(
                'intval',
                (array) $request->input('brand_ids', [])
            )))),
            'color_ids' => array_values(array_unique(array_filter(array_map(
                'intval',
                (array) $request->input('color_ids', [])
            )))),
            'gender' => $request->input('gender') ?: null,
            'type' => $request->input('type') ?: null,
            'price_min' => $request->filled('price_min') ? (float) $request->input('price_min') : null,
            'price_max' => $request->filled('price_max') ? (float) $request->input('price_max') : null,
            'size' => $request->input('size') ?: null,
            'size_uk' => $request->input('size_uk') ?: null,
            'availability' => $request->input('availability') ?: null,
        ];
    }

    public function productBySlug(string $slug): Product
    {
        return Product::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'brand',
                'category',
                'images',
                'variants.color',
                'variants.sizes',
                'reviews',
            ])
            ->firstOrFail();
    }

    /**
     * Related products for a product details page. Prefers same category, then
     * fills any remaining slots with same-brand products. Excludes the product
     * itself and inactive products.
     *
     * @return Collection<int, Product>
     */
    public function relatedProducts(Product $product, int $limit = 8): Collection
    {
        $with = ['brand', 'images', 'variants.color'];

        $primary = Product::query()
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->where('category_id', $product->category_id)
            ->with($with)
            ->latest()
            ->limit($limit)
            ->get();

        if ($primary->count() >= $limit) {
            return $primary;
        }

        $excludeIds = $primary->pluck('id')->push($product->id)->all();
        $needed = $limit - $primary->count();

        $secondary = Product::query()
            ->where('is_active', true)
            ->where('brand_id', $product->brand_id)
            ->whereNotIn('id', $excludeIds)
            ->with($with)
            ->latest()
            ->limit($needed)
            ->get();

        return $primary->concat($secondary)->values();
    }
}
