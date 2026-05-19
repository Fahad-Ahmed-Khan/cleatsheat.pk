<?php

namespace App\Http\Controllers\Web\Store;

use App\Http\Controllers\Controller;
use App\Models\MarketingSetting;
use App\Support\Seo\SeoPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageController extends Controller
{
    public function privacyPolicy(Request $request, SeoPresenter $seo): Response
    {
        return $this->show($request, 'privacy-policy', $seo);
    }

    public function termsAndConditions(Request $request, SeoPresenter $seo): Response
    {
        return $this->show($request, 'terms-and-conditions', $seo);
    }

    public function returnPolicy(Request $request, SeoPresenter $seo): Response
    {
        return $this->show($request, 'return-policy', $seo);
    }

    public function show(Request $request, string $slug, SeoPresenter $seo): Response
    {
        $pages = config('pages', []);
        if (! isset($pages[$slug])) {
            throw new NotFoundHttpException;
        }

        $page = $pages[$slug];
        $title = html_entity_decode(strip_tags((string) ($page['title'] ?? 'Page')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = (string) ($page['description'] ?? '');
        $body = $page['body'];

        if ($body === null && $slug === 'return-policy') {
            $summary = config('store.return_policy_summary', '');
            $body = '<p>'.e($summary).'</p>'
                .'<h2>Eligibility</h2>'
                .'<ul>'
                .'<li>Items must be unworn, in original condition, with tags and packaging where applicable.</li>'
                .'<li>Return requests should be raised within the window stated at checkout or on your order confirmation.</li>'
                .'<li>Pre-owned or clearance items may be final sale unless otherwise marked on the product page.</li>'
                .'</ul>'
                .'<h2>How to start a return</h2>'
                .'<p>Contact our support team with your order number and reason for return. We will confirm next steps, including pickup or drop-off instructions if applicable.</p>'
                .'<h2>Refunds</h2>'
                .'<p>Approved refunds are processed to the original payment method where possible. COD orders may be refunded via bank transfer or store credit as agreed with support.</p>'
                .'<h2>Exchanges</h2>'
                .'<p>Size exchanges depend on stock availability. We will do our best to offer an alternative size or store credit if exchange is not possible.</p>';
        }

        $canonical = rtrim(config('app.url'), '/').'/'.$slug;
        $seoTitle = $title.' — '.config('app.name');
        $marketing = MarketingSetting::query()->first();

        $seoPayload = $seo->mergeSocialTags([
            'title' => $seoTitle,
            'description' => $description,
            'canonical' => $canonical,
            'og_title' => $seoTitle,
            'og_description' => $description,
            'og_type' => 'website',
        ], $marketing?->default_og_image_url, $marketing?->twitter_site);

        return Inertia::render('Store/StaticPage', [
            'slug' => $slug,
            'title' => $title,
            'body' => (string) $body,
            'seo' => $seoPayload,
        ]);
    }
}
