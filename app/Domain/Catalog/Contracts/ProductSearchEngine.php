<?php

namespace App\Domain\Catalog\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductSearchEngine
{
    /**
     * Search products with tiered relevance and optional catalog filters.
     *
     * @param  array<string, mixed>  $filters  Same shape as parseSearchFilters output
     * @return array{paginator: LengthAwarePaginator, meta: array{query: string, fallback: ?string, total_exact: int, corrected_query: ?string}}
     */
    public function search(string $query, array $filters, int $perPage = 12): array;

    /**
     * Lightweight autocomplete suggestions.
     *
     * @return array{products: list<array<string, mixed>>, brands: list<array<string, mixed>>, categories: list<array<string, mixed>>, terms: list<string>}
     */
    public function suggest(string $query, int $productLimit = 8): array;
}
