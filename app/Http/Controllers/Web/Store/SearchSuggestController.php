<?php

namespace App\Http\Controllers\Web\Store;

use App\Domain\Catalog\CatalogQueryService;
use App\Domain\Catalog\SearchQueryNormalizer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchSuggestController extends Controller
{
    public function __invoke(Request $request, CatalogQueryService $catalog): JsonResponse
    {
        $raw = (string) $request->input('q', '');
        $normalized = SearchQueryNormalizer::normalize($raw);
        $minLength = (int) config('store.search_suggest_min_length', 2);

        if ($normalized !== '' && ! preg_match('/^[\pL\pN\s\-\'\.]+$/u', $normalized)) {
            return response()->json([
                'products' => [],
                'brands' => [],
                'categories' => [],
                'terms' => [],
            ]);
        }

        if (mb_strlen($normalized, 'UTF-8') < $minLength) {
            return response()->json([
                'products' => [],
                'brands' => [],
                'categories' => [],
                'terms' => [],
            ]);
        }

        return response()->json($catalog->suggest($normalized));
    }
}
