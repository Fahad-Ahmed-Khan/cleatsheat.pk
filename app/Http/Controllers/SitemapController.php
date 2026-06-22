<?php

namespace App\Http\Controllers;

use App\Support\Seo\SitemapBuilder;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SitemapController extends Controller
{
    public function index(SitemapBuilder $builder): Response
    {
        if ($builder->usesIndex()) {
            $xml = view('sitemap-index', [
                'sitemaps' => $builder->indexEntries(),
            ])->render();

            return response($xml, 200)->header('Content-Type', 'application/xml');
        }

        return $this->xmlResponse($builder->allUrls());
    }

    public function segment(string $segment, SitemapBuilder $builder): Response
    {
        if (! $builder->usesIndex()) {
            throw new NotFoundHttpException;
        }

        if (! in_array($segment, $builder->segmentNames(), true)) {
            throw new NotFoundHttpException;
        }

        $urls = $builder->urlsForSegment($segment);
        if ($urls === []) {
            throw new NotFoundHttpException;
        }

        return $this->xmlResponse($urls);
    }

    /**
     * @param  list<array{loc: string, lastmod?: string, changefreq?: string, priority?: string}>  $urls
     */
    private function xmlResponse(array $urls): Response
    {
        $xml = view('sitemap', ['urls' => collect($urls)])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
