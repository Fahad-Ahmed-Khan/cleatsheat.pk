<?php

namespace App\Http\Controllers\Web\Store;

use App\Http\Controllers\Controller;
use App\Models\ContentPost;
use App\Models\MarketingSetting;
use App\Support\Seo\SeoPresenter;
use App\Support\Storage\PublicAssetUrl;
use Inertia\Inertia;
use Inertia\Response;

class JournalController extends Controller
{
    public function index(SeoPresenter $seo): Response
    {
        $posts = ContentPost::query()
            ->published()
            ->orderByDesc('published_at')
            ->paginate(12);

        $posts->getCollection()->transform(fn (ContentPost $p) => [
            'slug' => $p->slug,
            'title' => $p->title,
            'excerpt' => $p->excerpt,
            'pillar_keyword' => $p->pillar_keyword,
            'published_at' => $p->published_at?->toIso8601String(),
        ]);

        $m = MarketingSetting::query()->first();
        $canonical = $seo->canonicalJournalIndex();
        $title = 'Journal — '.config('app.name');
        $description = 'Guides and stories about fit, sizing, and style — built for shoe shoppers in Pakistan.';

        $seoPayload = $seo->mergeSocialTags([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'og_title' => $title,
            'og_description' => $description,
            'og_type' => 'website',
        ], $m?->default_og_image_url, $m?->twitter_site);

        return Inertia::render('Store/JournalIndex', [
            'posts' => $posts,
            'seo' => $seoPayload,
        ]);
    }

    public function show(string $slug, SeoPresenter $seo): Response
    {
        $post = ContentPost::query()->where('slug', $slug)->published()->firstOrFail();

        $m = MarketingSetting::query()->first();
        $canonical = $seo->canonicalJournalPost($post);
        $title = ($post->meta_title ?: $post->title).' — '.config('app.name');
        $description = $post->meta_description ?? (string) ($post->excerpt ?? '');
        $featuredImage = PublicAssetUrl::resolve($post->featured_image_url);

        $schemas = [
            $seo->articleJsonLd($post, $canonical, $featuredImage ?: $m?->default_og_image_url),
            $seo->breadcrumbJsonLd([
                ['name' => 'Home', 'url' => $seo->canonicalHome()],
                ['name' => 'Journal', 'url' => $seo->canonicalJournalIndex()],
                ['name' => $post->title, 'url' => $canonical],
            ]),
        ];

        $seoPayload = $seo->mergeSocialTags([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'og_title' => $post->meta_title ?: $post->title,
            'og_description' => $description,
            'og_image' => $featuredImage,
            'og_type' => 'article',
            'schema_json' => $seo->encodeSchemas($schemas),
        ], $m?->default_og_image_url, $m?->twitter_site);

        return Inertia::render('Store/JournalShow', [
            'post' => [
                'title' => $post->title,
                'slug' => $post->slug,
                'body' => $post->body,
                'excerpt' => $post->excerpt,
                'pillar_keyword' => $post->pillar_keyword,
                'published_at' => $post->published_at?->toIso8601String(),
            ],
            'seo' => $seoPayload,
        ]);
    }
}
