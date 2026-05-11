<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\CatalogQueryService;
use App\Domain\Catalog\SizeChartResolver;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\ProductResource;
use App\Support\Api\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function categories(CatalogQueryService $catalog): JsonResponse
    {
        $data = CategoryResource::collection($catalog->rootCategories())->resolve();

        return ApiResponder::ok($data);
    }

    public function categoryProducts(string $slug, Request $request, CatalogQueryService $catalog): JsonResponse
    {
        $category = $catalog->categoryBySlug($slug);
        $perPage = min((int) $request->query('per_page', 12), 48);
        $filters = $catalog->parseProductListFilters($request);
        $products = $catalog->paginatedFilteredProductsForCategory($category, $filters, $perPage);
        $items = ProductResource::collection($products->items())->resolve();

        return ApiResponder::ok($items, 200, [
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
            'filters' => $filters,
        ]);
    }

    public function product(
        string $slug,
        CatalogQueryService $catalog,
        SizeChartResolver $sizeCharts,
    ): JsonResponse {
        $product = $catalog->productBySlug($slug);
        $data = (new ProductResource($product))->resolve();

        $chart = $sizeCharts->resolveForProduct($product);
        if ($chart) {
            $data['size_chart'] = [
                'id' => $chart->id,
                'name' => $chart->name,
                'rows' => $chart->rows->map(fn ($r) => [
                    'label' => $r->label,
                    'measurements' => $r->measurements,
                ])->values()->all(),
            ];
        }

        return ApiResponder::ok($data);
    }
}
