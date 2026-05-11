<?php

namespace App\Http\Controllers\Web\Store;

use App\Domain\Catalog\CatalogQueryService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\MarketingSetting;
use App\Support\Seo\SeoPresenter;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(CatalogQueryService $catalog, SeoPresenter $seo): Response
    {
        $featured = ProductResource::collection($catalog->featuredProducts(8))->resolve();
        $bestSellers = ProductResource::collection($catalog->bestSellingProducts(8))->resolve();
        $newArrivals = ProductResource::collection($catalog->newArrivals(8))->resolve();
        $trending = ProductResource::collection($catalog->trendingProducts(8))->resolve();
        $categories = CategoryResource::collection($catalog->rootCategories())->resolve();

        $m = MarketingSetting::query()->first();
        $defaultTitle = config('app.name').' — Premium footwear in Pakistan';
        $defaultDescription = 'Shop curated men\'s and women\'s shoes — sneakers, formal, sports, and running styles — with fast delivery, clear UK/EU/PK sizing, and trusted checkout.';
        $title = $m?->home_meta_title ?: $defaultTitle;
        $description = $m?->home_meta_description ?: $defaultDescription;
        $canonical = $seo->canonicalHome();

        $seoPayload = $seo->mergeSocialTags([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'og_title' => $title,
            'og_description' => $description,
            'og_type' => 'website',
        ], $m?->default_og_image_url, $m?->twitter_site);

        return Inertia::render('Store/Home', [
            'featured' => $featured,
            'bestSellers' => $bestSellers,
            'newArrivals' => $newArrivals,
            'trending' => $trending,
            'categories' => $categories,
            'hero' => [
                'title' => config('store.hero_title'),
                'subtitle' => config('store.hero_subtitle'),
                'badge' => config('store.hero_badge'),
            ],
            'seo' => $seoPayload,
        ]);
    }
}
