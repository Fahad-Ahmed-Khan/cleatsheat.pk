<?php

namespace App\Http\Controllers\Web\Store;

use App\Domain\Catalog\CatalogQueryService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\MarketingSetting;
use App\Support\Seo\SeoPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function __invoke(Request $request, CatalogQueryService $catalog, SeoPresenter $seo): Response
    {
        $filters = $catalog->parseShopFilters($request);
        $products = $catalog->paginatedFilteredAllProducts($filters);
        $options = $catalog->filterOptionsAll();

        $m = MarketingSetting::query()->first();
        $title = 'Shop all — '.config('app.name');
        $description = 'Browse the full collection — sneakers, formals, sports, and running. Filter by brand, colour, size, and price.';
        $canonical = rtrim(config('app.url'), '/').'/shop';

        $seoPayload = $seo->mergeSocialTags([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'og_title' => 'Shop all',
            'og_description' => $description,
            'og_type' => 'website',
        ], $m?->default_og_image_url, $m?->twitter_site);

        return Inertia::render('Store/Shop', [
            'products' => ProductResource::collection($products->items())->resolve(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
            'filterOptions' => [
                'brands' => $options['brands']->map(fn ($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'slug' => $b->slug,
                ])->values()->all(),
                'colors' => $options['colors']->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'hex' => $c->hex,
                ])->values()->all(),
                'sizes' => $options['sizes'],
                'sizes_uk' => $options['sizes_uk'] ?? [],
                'genders' => $options['genders'],
                'categories' => $options['categories']->map(fn ($cat) => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                ])->values()->all(),
                'price_min' => $options['price_min'],
                'price_max' => $options['price_max'],
            ],
            'filters' => $filters,
            'seo' => $seoPayload,
        ]);
    }
}
