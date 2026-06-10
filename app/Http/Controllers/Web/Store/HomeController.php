<?php

namespace App\Http\Controllers\Web\Store;

use App\Domain\Catalog\CatalogQueryService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Resources\Api\V1\ProductCardResource;
use App\Models\Brand;
use App\Models\ContentPost;
use App\Models\CustomerReview;
use App\Models\MarketingSetting;
use App\Models\Product;
use App\Models\StorefrontSetting;
use App\Support\Seo\SeoPresenter;
use App\Support\Storage\PublicAssetUrl;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(CatalogQueryService $catalog, SeoPresenter $seo): Response
    {
        $featured = ProductCardResource::collection($catalog->featuredProducts(8))->resolve();
        $bestSellers = ProductCardResource::collection($catalog->bestSellingProducts(8))->resolve();
        $newArrivals = ProductCardResource::collection($catalog->newArrivals(8))->resolve();
        $trending = ProductCardResource::collection($catalog->trendingProducts(8))->resolve();
        $categories = CategoryResource::collection($catalog->rootCategories())->resolve();

        $storefront = Schema::hasTable('storefront_settings')
            ? StorefrontSetting::query()->first()
            : null;
        $m = MarketingSetting::query()->first();

        $siteName = $storefront?->site_name ?: config('app.name');
        $defaultTitle = 'CleatSheat.pk — Used Football Boots & Cleats in Pakistan | FG, SG, AG';
        $defaultDescription = 'Shop original used football boots in Pakistan — FG, SG, AG & Turf cleats with UK/EU sizing, inspected condition, WhatsApp fit help, COD & fast nationwide delivery from Lahore to Karachi.';
        $title = $storefront?->default_meta_title
            ?: $m?->home_meta_title
            ?: $defaultTitle;
        $description = $storefront?->default_meta_description
            ?: $m?->home_meta_description
            ?: $defaultDescription;
        $canonical = $seo->canonicalHome();
        $ogImage = StorefrontSetting::resolveAssetUrl($storefront?->default_og_image_url)
            ?: PublicAssetUrl::resolve($m?->default_og_image_url);
        $twitterSite = $storefront?->twitter_site ?: $m?->twitter_site;

        $listForSchema = array_merge($featured, $trending);
        $schema = $seo->homeJsonLd($siteName, $canonical, $listForSchema, [
            'logo' => $ogImage,
            'telephone' => config('store.support_phone'),
            'sameAs' => [
                $storefront?->instagram_url ?? null,
                $storefront?->tiktok_url ?? null,
            ],
        ]);

        $seoPayload = $seo->mergeSocialTags([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'og_title' => $title,
            'og_description' => $description,
            'og_site_name' => $siteName,
            'og_type' => 'website',
            'schema_json' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], $ogImage, $twitterSite);

        $hero = $storefront
            ? $storefront->toHeroPayload()
            : [
                'title' => config('store.hero_title'),
                'subtitle' => config('store.hero_subtitle'),
                'badge' => config('store.hero_badge'),
                'image_url' => config('store.hero_image_url'),
                'cta_label' => null,
                'cta_url' => null,
            ];

        $promoBanner = $storefront
            ? $storefront->toPromoBannerPayload()
            : ['image_url' => null, 'link_url' => null, 'title' => null];

        $homeContent = $storefront
            ? $storefront->toHomeContentPayload()
            : [
                'testimonials' => StorefrontSetting::defaultTestimonials(),
                'social' => [
                    'instagram_url' => null,
                    'tiktok_url' => null,
                    'posts' => StorefrontSetting::defaultSocialPosts(),
                ],
                'seo_html' => null,
                'newsletter_enabled' => true,
            ];

        if ($storefront) {
            $social = $homeContent['social'];
            if ($storefront->instagram_url) {
                $social['instagram_url'] = $storefront->instagram_url;
            }
            if ($storefront->tiktok_url) {
                $social['tiktok_url'] = $storefront->tiktok_url;
            }
            $homeContent['social'] = $social;
        }

        if (Schema::hasTable('customer_reviews')) {
            $customerTestimonials = CustomerReview::testimonialsForHomepage();
            if ($customerTestimonials !== []) {
                $homeContent['testimonials'] = $customerTestimonials;
            }
        }

        $journalPosts = [];
        if (Schema::hasTable('content_posts')) {
            $journalPosts = ContentPost::query()
                ->published()
                ->orderByDesc('published_at')
                ->limit(3)
                ->get(['slug', 'title', 'excerpt', 'published_at'])
                ->map(fn (ContentPost $p) => [
                    'slug' => $p->slug,
                    'title' => $p->title,
                    'excerpt' => $p->excerpt,
                    'published_at' => $p->published_at?->toIso8601String(),
                ])
                ->all();
        }

        $shopProductCount = Product::query()->where('is_active', true)->count();

        $brands = Brand::query()
            ->whereHas('products', fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'logo_path'])
            ->map(fn (Brand $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'slug' => $b->slug,
                'logo_url' => PublicAssetUrl::resolve($b->logo_path),
            ])
            ->values()
            ->all();

        return Inertia::render('Store/Home', [
            'featured' => $featured,
            'bestSellers' => $bestSellers,
            'newArrivals' => $newArrivals,
            'trending' => $trending,
            'categories' => $categories,
            'shopProductCount' => $shopProductCount,
            'brands' => $brands,
            'hero' => $hero,
            'promoBanner' => $promoBanner,
            'homeContent' => $homeContent,
            'journalPosts' => $journalPosts,
            'seo' => $seoPayload,
        ]);
    }
}
