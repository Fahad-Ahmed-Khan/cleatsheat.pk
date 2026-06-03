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
        $title = 'Shop Football Shoes, Cleats & Gear in Pakistan | '.config('app.name');
        $description = 'Shop all football shoes, cleats, grippers, socks & accessories in Pakistan. Filter by brand, surface (FG/SG/AG/Turf), UK/EU size and price. COD and fast nationwide delivery.';
        $canonical = rtrim(config('app.url'), '/').'/shop';

        $productList = ProductResource::collection($products->items())->resolve();

        $schemas = [
            $seo->collectionJsonLd('Shop all — '.config('app.name'), $description, $canonical, $productList),
            $seo->breadcrumbJsonLd([
                ['name' => 'Home', 'url' => $seo->canonicalHome()],
                ['name' => 'Shop', 'url' => $canonical],
            ]),
        ];

        $seoPayload = $seo->mergeSocialTags([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'og_title' => 'Shop all football gear',
            'og_description' => $description,
            'og_type' => 'website',
            'schema_json' => $seo->encodeSchemas($schemas),
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
