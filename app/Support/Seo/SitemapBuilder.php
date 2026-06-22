<?php

namespace App\Support\Seo;

use App\Models\Category;
use App\Models\ContentPost;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

final class SitemapBuilder
{
    public function chunkSize(): int
    {
        return max(1, (int) config('seo.sitemap_chunk_size', 10_000));
    }

    public function indexThreshold(): int
    {
        return max(1, (int) config('seo.sitemap_index_threshold', 45_000));
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public function usesIndex(): bool
    {
        return $this->totalUrlCount() > $this->indexThreshold();
    }

    public function totalUrlCount(): int
    {
        $count = 2 + count(config('pages', []));
        $count += Category::query()->active()->count();
        $count += Product::query()->where('is_active', true)->count();

        if (Schema::hasTable('content_posts')) {
            $count += 1;
            $count += ContentPost::query()->published()->count();
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    public function segmentNames(): array
    {
        if (! $this->usesIndex()) {
            return [];
        }

        $segments = ['core', 'categories'];

        $productChunks = Product::query()->where('is_active', true)->count();
        if ($productChunks > 0) {
            $pages = (int) ceil($productChunks / $this->chunkSize());
            for ($i = 1; $i <= $pages; $i++) {
                $segments[] = 'products-'.$i;
            }
        }

        if (Schema::hasTable('content_posts') && ContentPost::query()->published()->exists()) {
            $segments[] = 'journal';
        }

        return $segments;
    }

    /**
     * @return list<array{loc: string, lastmod?: string, changefreq?: string, priority?: string}>
     */
    public function allUrls(): array
    {
        return array_merge(
            $this->coreUrls(),
            $this->categoryUrls(),
            $this->productUrls(),
            $this->journalUrls(),
        );
    }

    /**
     * @return list<array{loc: string, lastmod: string}>
     */
    public function indexEntries(): array
    {
        $base = $this->baseUrl();
        $now = now()->toAtomString();

        return array_map(
            fn (string $segment) => [
                'loc' => $base.'/sitemap/'.$segment.'.xml',
                'lastmod' => $now,
            ],
            $this->segmentNames(),
        );
    }

    /**
     * @return list<array{loc: string, lastmod?: string, changefreq?: string, priority?: string}>
     */
    public function urlsForSegment(string $segment): array
    {
        if (preg_match('/^products-(\d+)$/', $segment, $m)) {
            return $this->productUrls((int) $m[1]);
        }

        return match ($segment) {
            'core' => $this->coreUrls(),
            'categories' => $this->categoryUrls(),
            'journal' => $this->journalUrls(),
            default => [],
        };
    }

    /**
     * @return list<array{loc: string, lastmod?: string, changefreq?: string, priority?: string}>
     */
    private function coreUrls(): array
    {
        $base = $this->baseUrl();
        $staticLastmod = $this->staticPagesLastmod();

        $urls = [
            ['loc' => $base.'/', 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => $base.'/shop', 'changefreq' => 'daily', 'priority' => '0.9'],
        ];

        foreach (array_keys(config('pages', [])) as $slug) {
            $urls[] = [
                'loc' => $base.'/'.$slug,
                'lastmod' => $staticLastmod,
                'changefreq' => 'monthly',
                'priority' => '0.5',
            ];
        }

        if (Schema::hasTable('content_posts')) {
            $urls[] = [
                'loc' => $base.'/journal',
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ];
        }

        return $urls;
    }

    /**
     * @return list<array{loc: string, lastmod?: string, changefreq?: string, priority?: string}>
     */
    private function categoryUrls(): array
    {
        $base = $this->baseUrl();
        $urls = [];

        foreach (Category::query()->active()->get(['slug', 'updated_at']) as $c) {
            $urls[] = [
                'loc' => $base.'/c/'.$c->slug,
                'lastmod' => $c->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        }

        return $urls;
    }

    /**
     * @return list<array{loc: string, lastmod?: string, changefreq?: string, priority?: string}>
     */
    private function productUrls(?int $chunk = null): array
    {
        $base = $this->baseUrl();
        $query = Product::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->select(['slug', 'updated_at']);

        if ($chunk !== null) {
            $query->skip(($chunk - 1) * $this->chunkSize())->take($this->chunkSize());
        }

        $urls = [];
        foreach ($query->get() as $p) {
            $urls[] = [
                'loc' => $base.'/p/'.$p->slug,
                'lastmod' => $p->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.9',
            ];
        }

        return $urls;
    }

    /**
     * @return list<array{loc: string, lastmod?: string, changefreq?: string, priority?: string}>
     */
    private function journalUrls(): array
    {
        if (! Schema::hasTable('content_posts')) {
            return [];
        }

        $base = $this->baseUrl();
        $urls = [];

        foreach (ContentPost::query()->published()->get(['slug', 'updated_at']) as $post) {
            $urls[] = [
                'loc' => $base.'/journal/'.$post->slug,
                'lastmod' => $post->updated_at?->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.55',
            ];
        }

        return $urls;
    }

    private function staticPagesLastmod(): string
    {
        $path = config_path('pages.php');
        $mtime = is_file($path) ? filemtime($path) : false;

        return Carbon::createFromTimestamp($mtime ?: time())->toAtomString();
    }
}
