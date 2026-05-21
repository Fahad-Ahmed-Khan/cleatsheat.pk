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

class CategoryController extends Controller
{
    public function __invoke(string $slug, Request $request, CatalogQueryService $catalog, SeoPresenter $seo): Response
    {
        $category = $catalog->categoryBySlug($slug);
        $category->load(['children' => fn ($q) => $q->active()->orderBy('sort_order')]);
        $filters = $catalog->parseCategoryListFilters($request);
        $products = $catalog->paginatedFilteredProductsForCategory($category, $filters);
        $options = $catalog->filterOptionsForCategory($category);

        $m = MarketingSetting::query()->first();
        $canonical = $seo->canonicalCategory($category->slug);
        $description = $seo->categoryIntentDescription($category);
        $title = ($category->meta_title ?: $category->name).' — '.config('app.name');

        $ogImage = $category->og_image_url ?: null;

        $seoPayload = $seo->mergeSocialTags([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'og_title' => $category->meta_title ?: $category->name,
            'og_description' => $description,
            'og_image' => $ogImage,
            'og_type' => 'website',
        ], $m?->default_og_image_url, $m?->twitter_site);

        return Inertia::render('Store/Category', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'meta_title' => $category->meta_title,
                'meta_description' => $category->meta_description,
                'intro_html' => $category->intro_html,
                'og_image_url' => $category->og_image_url,
                'children' => $category->children->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                ])->values()->all(),
            ],
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
                'price_min' => $options['price_min'],
                'price_max' => $options['price_max'],
            ],
            'filters' => $filters,
            'seo' => $seoPayload,
        ]);
    }
}
