<?php

namespace App\Domain\Catalog;

use App\Domain\Catalog\Contracts\ProductSearchEngine;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class MySqlProductSearchEngine implements ProductSearchEngine
{
    public function __construct(
        private ProductListFilterApplicator $filters,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function search(string $query, array $filters, int $perPage = 12): array
    {
        $normalized = SearchQueryNormalizer::normalize($query);
        $meta = [
            'query' => $normalized,
            'fallback' => null,
            'total_exact' => 0,
            'corrected_query' => null,
        ];

        if ($normalized === '') {
            return [
                'paginator' => $this->emptyPaginator($perPage),
                'meta' => $meta,
            ];
        }

        $result = $this->runSearch($normalized, $filters, $perPage, fuzzy: false);
        $meta['total_exact'] = $result->total();

        if ($result->total() === 0) {
            $result = $this->runSearch($normalized, $filters, $perPage, fuzzy: true);
            if ($result->total() > 0) {
                $meta['fallback'] = 'fuzzy';
            }
        }

        return ['paginator' => $result, 'meta' => $meta];
    }

    public function suggest(string $query, int $productLimit = 8): array
    {
        $normalized = SearchQueryNormalizer::normalize($query);
        $minLength = (int) config('store.search_suggest_min_length', 2);

        if (mb_strlen($normalized, 'UTF-8') < $minLength) {
            return ['products' => [], 'brands' => [], 'categories' => [], 'terms' => []];
        }

        $cacheKey = 'search:suggest:'.md5(mb_strtolower($normalized, 'UTF-8'));
        $ttl = (int) config('store.search_suggest_cache_ttl', 120);

        return Cache::remember($cacheKey, $ttl, fn () => $this->buildSuggestions($normalized, $productLimit));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function runSearch(string $normalized, array $filters, int $perPage, bool $fuzzy): LengthAwarePaginator
    {
        $query = Product::query()
            ->where('is_active', true)
            ->with(['brand', 'category', 'images', 'variants.color', 'variants.sizes']);

        $this->filters->apply($query, $filters);

        $this->applySearchMatch($query, $normalized, $fuzzy);

        $hasUserSort = filled($filters['sort'] ?? null);
        if (! $hasUserSort) {
            $this->applySearchOrdering($query, $normalized);
        } else {
            $this->filters->applySort($query, $filters);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    private function applySearchMatch(Builder $query, string $normalized, bool $fuzzy): void
    {
        if ($this->supportsFullText()) {
            $this->applyMysqlSearchMatch($query, $normalized, $fuzzy);

            return;
        }

        $this->applyLikeSearchMatch($query, $normalized, $fuzzy);
    }

    private function applySearchOrdering(Builder $query, string $normalized): void
    {
        if ($this->supportsFullText()) {
            $bindings = $this->scoreBindings($normalized, false);
            $scoreSql = $this->scoreExpressionSql();

            $query->selectRaw('products.*, ('.$scoreSql.') as search_score', $bindings);
            $query->orderByDesc('search_score');
        } else {
            $query->selectRaw('products.*, 0 as search_score');
        }

        $query->orderByDesc(
            DB::raw('EXISTS(
                SELECT 1 FROM product_variants pv
                INNER JOIN variant_sizes vs ON vs.product_variant_id = pv.id
                WHERE pv.product_id = products.id AND vs.stock_qty > 0
            )')
        );

        $query->orderBy(
            ProductVariant::query()->select('price')->whereColumn('product_id', 'products.id')->orderBy('price')->limit(1),
            'asc'
        );
        $query->orderBy('products.id');
    }

    private function applyMysqlSearchMatch(Builder $query, string $normalized, bool $fuzzy): void
    {
        $slugQ = SearchQueryNormalizer::slugCandidate($normalized);
        $prefix = $normalized.'%';
        $boolQ = SearchQueryNormalizer::booleanQuery($normalized);
        $tokens = SearchQueryNormalizer::tokens($normalized);

        $query->where(function (Builder $q) use ($normalized, $slugQ, $prefix, $boolQ, $tokens, $fuzzy) {
            $q->where('products.slug', $slugQ)
                ->orWhereRaw('LOWER(products.name) = ?', [mb_strtolower($normalized, 'UTF-8')])
                ->orWhere('products.slug', 'like', $slugQ.'%')
                ->orWhere('products.name', 'like', $prefix);

            if ($boolQ !== '') {
                $q->orWhereRaw('MATCH(products.search_text) AGAINST(? IN BOOLEAN MODE)', [$boolQ]);
            }

            if ($fuzzy) {
                foreach ($tokens as $i => $token) {
                    if (mb_strlen($token, 'UTF-8') >= 2) {
                        $q->orWhere('products.search_text', 'like', '%'.$token.'%');
                    }
                }
            }
        });
    }

    private function applyLikeSearchMatch(Builder $query, string $normalized, bool $fuzzy): void
    {
        $slugQ = SearchQueryNormalizer::slugCandidate($normalized);
        $prefix = $normalized.'%';
        $tokens = SearchQueryNormalizer::tokens($normalized);

        $query->where(function (Builder $q) use ($normalized, $slugQ, $prefix, $tokens, $fuzzy) {
            $q->where('products.slug', $slugQ)
                ->orWhereRaw('LOWER(products.name) = ?', [mb_strtolower($normalized, 'UTF-8')])
                ->orWhere('products.slug', 'like', $slugQ.'%')
                ->orWhere('products.name', 'like', $prefix);

            foreach ($tokens as $token) {
                if (mb_strlen($token, 'UTF-8') >= ($fuzzy ? 2 : 3)) {
                    $q->orWhere('products.search_text', 'like', '%'.$token.'%');
                }
            }
        });
    }

    /**
     * @return list<mixed>
     */
    private function scoreBindings(string $normalized, bool $includeFuzzy): array
    {
        $slugQ = SearchQueryNormalizer::slugCandidate($normalized);
        $prefix = $normalized.'%';
        $boolQ = SearchQueryNormalizer::booleanQuery($normalized);

        return [
            $slugQ, $normalized,
            $slugQ.'%', $prefix, $prefix,
            $boolQ, $normalized,
        ];
    }

    private function scoreExpressionSql(): string
    {
        return 'GREATEST(
            CASE WHEN products.slug = ? OR LOWER(products.name) = LOWER(?) THEN 41000 ELSE 0 END,
            CASE WHEN products.slug LIKE ? OR products.name LIKE ? OR products.search_text LIKE ? THEN 35000 ELSE 0 END,
            CASE WHEN MATCH(products.search_text) AGAINST(? IN BOOLEAN MODE)
                THEN 2000 + (COALESCE(MATCH(products.search_text) AGAINST(? IN NATURAL LANGUAGE MODE), 0) * 100)
                ELSE 0 END
        )';
    }

    private function supportsFullText(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    /**
     * @return array{products: list<array<string, mixed>>, brands: list<array<string, mixed>>, categories: list<array<string, mixed>>, terms: list<string>}
     */
    private function buildSuggestions(string $normalized, int $productLimit): array
    {
        $prefix = $normalized.'%';
        $boolQ = SearchQueryNormalizer::booleanQuery($normalized);

        $productQuery = Product::query()
            ->where('is_active', true)
            ->with(['brand', 'images', 'variants'])
            ->limit($productLimit);

        if ($this->supportsFullText() && $boolQ !== '') {
            $productQuery
                ->where(function (Builder $q) use ($normalized, $prefix, $boolQ) {
                    $q->where('name', 'like', $prefix)
                        ->orWhereRaw('MATCH(search_text) AGAINST(? IN BOOLEAN MODE)', [$boolQ])
                        ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($normalized, 'UTF-8')]);
                })
                ->selectRaw('products.*, COALESCE(MATCH(search_text) AGAINST(? IN NATURAL LANGUAGE MODE), 0) as ft_score', [$normalized])
                ->orderByDesc('ft_score')
                ->orderBy('name');
        } else {
            $productQuery
                ->where(function (Builder $q) use ($normalized, $prefix) {
                    $q->where('name', 'like', $prefix)
                        ->orWhere('search_text', 'like', '%'.$normalized.'%')
                        ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($normalized, 'UTF-8')]);
                })
                ->orderBy('name');
        }

        $products = $productQuery->get()->map(fn (Product $p) => $this->suggestProductShape($p))->values()->all();

        $brands = Brand::query()
            ->where('name', 'like', $prefix)
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'slug'])
            ->map(fn (Brand $b) => ['id' => $b->id, 'name' => $b->name, 'slug' => $b->slug])
            ->values()
            ->all();

        $categories = Category::query()
            ->active()
            ->where('name', 'like', $prefix)
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'slug'])
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug])
            ->values()
            ->all();

        $terms = $this->popularTerms($normalized);

        return [
            'products' => $products,
            'brands' => $brands,
            'categories' => $categories,
            'terms' => $terms,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function suggestProductShape(Product $product): array
    {
        $image = $product->images->first();
        $minPrice = $product->variants
            ->where('is_active', true)
            ->min('price');

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'brand' => $product->brand ? ['name' => $product->brand->name] : null,
            'thumbnail' => $image?->path,
            'price_from' => $minPrice !== null ? (float) $minPrice : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function popularTerms(string $normalized): array
    {
        $defaults = (array) config('store.search_popular_terms', [
            'nike mercurial', 'adidas predator', 'football boots', 'uk 9',
        ]);

        $prefix = mb_strtolower($normalized, 'UTF-8');

        return array_values(array_slice(array_filter(
            $defaults,
            static fn (string $term): bool => str_starts_with(mb_strtolower($term, 'UTF-8'), $prefix)
        ), 0, 3));
    }

    private function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return Product::query()->whereRaw('1 = 0')->paginate($perPage);
    }
}
