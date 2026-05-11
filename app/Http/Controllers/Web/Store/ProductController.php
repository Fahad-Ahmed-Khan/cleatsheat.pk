<?php

namespace App\Http\Controllers\Web\Store;

use App\Domain\Catalog\CatalogQueryService;
use App\Domain\Catalog\SizeChartResolver;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\MarketingSetting;
use App\Support\Seo\SeoPresenter;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __invoke(
        string $slug,
        CatalogQueryService $catalog,
        SizeChartResolver $sizeCharts,
        SeoPresenter $seo,
    ): Response {
        $product = $catalog->productBySlug($slug);
        $chart = $sizeCharts->resolveForProduct($product);

        $canonical = $seo->canonicalProduct($product);
        $primaryImage = $product->images->first()?->path;
        $description = $seo->productDescriptionForSeo($product);
        $m = MarketingSetting::query()->first();

        $seoPayload = $seo->mergeSocialTags([
            'title' => ($product->meta_title ?: $product->name).' — '.config('app.name'),
            'description' => $description,
            'og_title' => $product->meta_title ?: $product->name,
            'og_description' => $description,
            'canonical' => $canonical,
            'og_image' => $primaryImage,
            'og_type' => 'product',
            'twitter_card' => 'summary_large_image',
            'schema_json' => json_encode($seo->productJsonLd($product, $canonical), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], $m?->default_og_image_url, $m?->twitter_site);

        $sizeChartPayload = null;
        if ($chart) {
            $sizeChartPayload = [
                'id' => $chart->id,
                'name' => $chart->name,
                'rows' => $chart->rows->map(fn ($r) => [
                    'label' => $r->label,
                    'uk_size' => $r->uk_size,
                    'eu_size' => $r->eu_size,
                    'pk_size' => $r->pk_size,
                    'foot_cm' => $r->foot_cm !== null ? (float) $r->foot_cm : null,
                    'measurements' => $r->measurements,
                ])->values()->all(),
            ];
        }

        $related = $catalog->relatedProducts($product, 8)->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'brand' => $p->brand ? ['name' => $p->brand->name, 'slug' => $p->brand->slug] : null,
            'images' => $p->images->map(fn ($img) => [
                'path' => $img->path,
                'alt' => $img->alt,
            ])->values()->all(),
            'variants' => $p->variants->map(fn ($v) => [
                'id' => $v->id,
                'price' => (float) $v->price,
                'compare_at_price' => $v->compare_at_price !== null ? (float) $v->compare_at_price : null,
            ])->values()->all(),
        ])->values()->all();

        return Inertia::render('Store/Product', [
            'product' => (new ProductResource($product))->resolve(),
            'reviews' => $product->reviews->map(fn ($r) => [
                'id' => $r->id,
                'author_display' => $r->author_display,
                'rating' => $r->rating,
                'fit_feedback' => $r->fit_feedback,
                'title' => $r->title,
                'body' => $r->body,
                'created_at' => $r->created_at->toIso8601String(),
            ])->values()->all(),
            'sizeChart' => $sizeChartPayload,
            'relatedProducts' => $related,
            'seo' => $seoPayload,
        ]);
    }
}
