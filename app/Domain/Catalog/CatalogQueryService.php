<?php

namespace App\Domain\Catalog;

use App\Domain\Catalog\Contracts\ProductSearchEngine;
use App\Enums\Gender;
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
    public function __construct(
        private ProductSearchEngine $searchEngine,
        private ProductListFilterApplicator $filterApplicator,
        private SearchQueryLogger $searchLogger,
    ) {}

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
            ->with(['brand', 'category', 'images', 'variants.color', 'variants.sizes'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, Product> */
    public function newArrivals(int $limit = 8): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->with(['brand', 'category', 'images', 'variants.color', 'variants.sizes'])
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
            ->with(['brand', 'category', 'images', 'variants.color', 'variants.sizes'])
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
            ->with(['brand', 'category', 'images', 'variants.color', 'variants.sizes'])
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
            ->active()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with(['children' => fn ($q) => $q->active()->orderBy('sort_order')])
            ->get();
    }

    /**
     * Surface subcategories for the home page tile row (FG, SG, AG, etc.).
     *
     * @return Collection<int, Category>
     */
    public function surfaceCategories(): Collection
    {
        $parentSlug = (string) config('store.surface_parent_slug', '');
        if ($parentSlug === '') {
            return new Collection;
        }

        $parent = Category::query()->active()->where('slug', $parentSlug)->first();
        if ($parent === null) {
            return new Collection;
        }

        return Category::query()
            ->active()
            ->where('parent_id', $parent->id)
            ->orderBy('sort_order')
            ->get();
    }

    public function categoryBySlug(string $slug): Category
    {
        return Category::query()->active()->where('slug', $slug)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    /**
     * @return list<int>
     */
    private function categoryScopeIds(Category $category): array
    {
        return $category->selfAndDescendantIds();
    }

    public function filterOptionsForCategory(Category $category): array
    {
        $categoryIds = $this->categoryScopeIds($category);

        $base = Product::query()
            ->whereIn('category_id', $categoryIds)
            ->where('is_active', true);

        $brandIds = (clone $base)->pluck('brand_id')->unique()->filter()->values();
        $brands = $brandIds->isEmpty()
            ? collect()
            : Brand::query()->whereIn('id', $brandIds)->orderBy('name')->get(['id', 'name', 'slug']);

        $colorIds = ProductVariant::query()
            ->whereHas('product', fn (Builder $q) => $q->whereIn('category_id', $categoryIds)->where('is_active', true))
            ->pluck('color_id')
            ->unique()
            ->filter()
            ->values();

        $colors = $colorIds->isEmpty()
            ? collect()
            : Color::query()->whereIn('id', $colorIds)->orderBy('name')->get(['id', 'name', 'slug', 'hex']);

        $priceAgg = ProductVariant::query()
            ->whereHas('product', fn (Builder $q) => $q->whereIn('category_id', $categoryIds)->where('is_active', true))
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        $sizes = DB::table('variant_sizes')
            ->join('product_variants', 'variant_sizes.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->whereIn('products.category_id', $categoryIds)
            ->where('products.is_active', true)
            ->distinct()
            ->orderBy('variant_sizes.size_label')
            ->pluck('variant_sizes.size_label')
            ->values()
            ->all();

        $sizesUk = $this->ukSizeOptions(
            DB::table('variant_sizes')
                ->join('product_variants', 'variant_sizes.product_variant_id', '=', 'product_variants.id')
                ->join('products', 'product_variants.product_id', '=', 'products.id')
                ->whereIn('products.category_id', $categoryIds)
                ->where('products.is_active', true)
        );

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
            'sizes_uk' => $sizesUk,
            'genders' => $genders,
            'price_min' => $priceAgg?->min_price !== null ? (float) $priceAgg->min_price : 0,
            'price_max' => $priceAgg?->max_price !== null ? (float) $priceAgg->max_price : 0,
        ];
    }

    /**
     * Collect a distinct, naturally-sorted list of UK size labels.
     *
     * @return array<int, string>
     */
    private function ukSizeOptions(\Illuminate\Database\Query\Builder $query): array
    {
        $values = (clone $query)
            ->whereNotNull('variant_sizes.uk_size')
            ->where('variant_sizes.uk_size', '!=', '')
            ->distinct()
            ->pluck('variant_sizes.uk_size')
            ->map(static fn ($v) => trim((string) $v))
            ->filter(static fn (string $v) => $v !== '')
            ->unique()
            ->values()
            ->all();

        usort($values, static function (string $a, string $b): int {
            $af = is_numeric($a) ? (float) $a : null;
            $bf = is_numeric($b) ? (float) $b : null;
            if ($af !== null && $bf !== null) {
                return $af <=> $bf;
            }
            if ($af !== null) {
                return -1;
            }
            if ($bf !== null) {
                return 1;
            }

            return strcmp($a, $b);
        });

        return $values;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginatedFilteredProductsForCategory(Category $category, array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $categoryIds = $this->categoryScopeIds($category);

        $query = Product::query()
            ->whereIn('category_id', $categoryIds)
            ->where('is_active', true)
            ->with(['brand', 'category', 'images', 'variants.color', 'variants.sizes']);

        $this->filterApplicator->apply($query, $filters, includeCategoryIds: false);
        $this->applyProductSort($query, $filters);

        return $query
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

        $sizesUk = $this->ukSizeOptions(
            DB::table('variant_sizes')
                ->join('product_variants', 'variant_sizes.product_variant_id', '=', 'product_variants.id')
                ->join('products', 'product_variants.product_id', '=', 'products.id')
                ->where('products.is_active', true)
        );

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
            'sizes_uk' => $sizesUk,
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
            ->with(['brand', 'category', 'images', 'variants.color', 'variants.sizes']);

        $this->filterApplicator->apply($query, $filters);
        $this->applyProductSort($query, $filters);

        return $query
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Search results with tiered relevance, filters, and graceful fuzzy fallback.
     *
     * @param  array<string, mixed>  $filters
     * @return array{paginator: LengthAwarePaginator, meta: array<string, mixed>, fallback_products: Collection<int, Product>}
     */
    public function paginatedSearchResults(array $filters, int $perPage = 12, ?string $ip = null): array
    {
        $q = trim((string) ($filters['q'] ?? ''));

        if ($q === '') {
            $paginator = $this->paginatedFilteredAllProducts($filters, $perPage);

            return [
                'paginator' => $paginator,
                'meta' => [
                    'query' => '',
                    'fallback' => null,
                    'total_exact' => $paginator->total(),
                    'corrected_query' => null,
                ],
                'fallback_products' => new Collection,
            ];
        }

        $result = $this->searchEngine->search($q, $filters, $perPage);
        $paginator = $result['paginator'];
        $meta = $result['meta'];

        $fallbackProducts = new Collection;
        if ($paginator->total() === 0) {
            $meta['fallback'] = 'popular';
            $fallbackProducts = $this->bestSellingProducts(12);
        }

        $this->searchLogger->log($q, $paginator->total(), $ip);

        return [
            'paginator' => $paginator,
            'meta' => $meta,
            'fallback_products' => $fallbackProducts,
        ];
    }

    /**
     * @return array{products: list<array<string, mixed>>, brands: list<array<string, mixed>>, categories: list<array<string, mixed>>, terms: list<string>}
     */
    public function suggest(string $query): array
    {
        return $this->searchEngine->suggest($query);
    }

    public function normalizeSearchQuery(string $query): string
    {
        return SearchQueryNormalizer::normalize($query);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyProductSort(Builder $query, array $filters): void
    {
        $sort = $filters['sort'] ?? null;
        if ($sort === null) {
            $query->latest();

            return;
        }

        $this->filterApplicator->applySort($query, $filters);
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
        $base['sort'] = $this->parseSortFilter($request);

        return $base;
    }

    /**
     * Shop/search filters including optional search query `q`.
     *
     * @return array<string, mixed>
     */
    public function parseSearchFilters(Request $request): array
    {
        $filters = $this->parseShopFilters($request);
        $filters['q'] = $this->normalizeSearchQuery((string) $request->input('q', ''));

        return $filters;
    }

    /**
     * Query-string filters for category product listings (includes sort).
     *
     * @return array<string, mixed>
     */
    public function parseCategoryListFilters(Request $request): array
    {
        $base = $this->parseProductListFilters($request);
        $base['sort'] = $this->parseSortFilter($request);

        return $base;
    }

    private function parseSortFilter(Request $request): ?string
    {
        $sort = $request->input('sort');

        return in_array($sort, ['price_asc', 'price_desc', 'newest'], true) ? $sort : null;
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
            'size_uk' => $this->normalizeStringList($request->input('size_uk')),
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
        $with = ['brand', 'category', 'images', 'variants.color', 'variants.sizes'];

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
