<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ContentPost;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $base = rtrim(config('app.url'), '/');

        $urls = collect([
            ['loc' => $base.'/', 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => $base.'/shop', 'changefreq' => 'daily', 'priority' => '0.9'],
        ]);

        foreach (array_keys(config('pages', [])) as $slug) {
            $urls->push([
                'loc' => $base.'/'.$slug,
                'changefreq' => 'monthly',
                'priority' => '0.5',
            ]);
        }

        foreach (Category::query()->active()->get(['slug', 'updated_at']) as $c) {
            $urls->push([
                'loc' => $base.'/c/'.$c->slug,
                'lastmod' => $c->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ]);
        }

        foreach (Product::query()->where('is_active', true)->get(['slug', 'updated_at']) as $p) {
            $urls->push([
                'loc' => $base.'/p/'.$p->slug,
                'lastmod' => $p->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.9',
            ]);
        }

        if (Schema::hasTable('content_posts')) {
            $urls->push([
                'loc' => $base.'/journal',
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ]);
            foreach (ContentPost::query()->published()->get(['slug', 'updated_at']) as $post) {
                $urls->push([
                    'loc' => $base.'/journal/'.$post->slug,
                    'lastmod' => $post->updated_at?->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.55',
                ]);
            }
        }

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
