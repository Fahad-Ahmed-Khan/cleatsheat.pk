<?php

namespace App\Http\Controllers\Web\Store;

use App\Domain\Catalog\CatalogQueryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\SearchQueryRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\MarketingSetting;
use App\Support\Seo\SeoPresenter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __invoke(SearchQueryRequest $request, CatalogQueryService $catalog, SeoPresenter $seo): Response|RedirectResponse
    {
        $filters = $catalog->parseSearchFilters($request);
        $q = trim((string) ($filters['q'] ?? ''));

        if ($q === '' && ! $request->hasAny([
            'category_ids', 'brand_ids', 'color_ids', 'gender', 'type',
            'price_min', 'price_max', 'size', 'size_uk', 'availability', 'sort',
        ])) {
            return redirect()->route('store.shop');
        }

        $search = $catalog->paginatedSearchResults($filters, 12, $request->ip());
        $products = $search['paginator'];
        $searchMeta = $search['meta'];
        $fallbackProducts = $search['fallback_products'];

        $options = $catalog->filterOptionsAll();
        $m = MarketingSetting::query()->first();

        $canonical = rtrim(config('app.url'), '/').'/search';
        $title = $q !== ''
            ? $seo->searchTitle($q)
            : 'Search football shoes & gear | '.config('app.name');
        $description = $q !== ''
            ? $seo->searchDescription($q, $products->total())
            : 'Search our catalogue of football boots, cleats and gear in Pakistan.';

        $productList = ProductResource::collection($products->items())->resolve();

        $schemas = [
            $seo->collectionJsonLd(
                $q !== '' ? 'Search: '.$q : 'Search',
                $description,
                $canonical,
                $productList
            ),
            $seo->breadcrumbJsonLd([
                ['name' => 'Home', 'url' => $seo->canonicalHome()],
                ['name' => 'Search', 'url' => $canonical],
            ]),
        ];

        $seoPayload = $seo->mergeSocialTags([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $q !== '' ? 'noindex, follow' : null,
            'og_title' => $title,
            'og_description' => $description,
            'og_type' => 'website',
            'schema_json' => $seo->encodeSchemas($schemas),
        ], $m?->default_og_image_url, $m?->twitter_site);

        return Inertia::render('Store/Search', [
            'products' => $productList,
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
            'filterOptions' => $this->mapFilterOptions($options),
            'filters' => $filters,
            'searchMeta' => $searchMeta,
            'fallbackProducts' => $fallbackProducts->isNotEmpty()
                ? ProductResource::collection($fallbackProducts)->resolve()
                : [],
            'seo' => $seoPayload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function mapFilterOptions(array $options): array
    {
        return [
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
        ];
    }
}
