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

    public function paymentPolicy(Request $request, SeoPresenter $seo): Response
    {
        return $this->show($request, 'payment-policy', $seo);
    }

    public function disclaimer(Request $request, SeoPresenter $seo): Response
    {
        return $this->show($request, 'disclaimer', $seo);
    }

    public function shippingPolicy(Request $request, SeoPresenter $seo): Response
    {
        return $this->show($request, 'shipping-policy', $seo);
    }

    public function about(Request $request, SeoPresenter $seo): Response
    {
        return $this->show($request, 'about', $seo);
    }

    public function faq(Request $request, SeoPresenter $seo): Response
    {
        return $this->show($request, 'faq', $seo);
    }

    public function contact(Request $request, SeoPresenter $seo): Response
    {
        return $this->show($request, 'contact', $seo);
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
        $body = $page['body'] ?? null;

        if ($body === null && $slug === 'faq') {
            $body = $this->faqBodyFromConfig($page['faqs'] ?? []);
        }

        $canonical = rtrim(config('app.url'), '/').'/'.$slug;
        $seoTitle = $title.' — '.$seo->storeName();
        $marketing = MarketingSetting::query()->first();

        $schemas = [];
        if ($slug === 'faq' && ! empty($page['faqs'])) {
            $schemas[] = $seo->faqJsonLd($page['faqs']);
        }
        if ($slug === 'contact' && ! empty($page['local_business'])) {
            $lb = $page['local_business'];
            $schemas[] = $seo->localBusinessJsonLd([
                'name' => $seo->storeName(),
                'url' => $canonical,
                'telephone' => $lb['telephone'] ?? config('store.support_phone'),
                'email' => $lb['email'] ?? null,
                'streetAddress' => $lb['streetAddress'] ?? null,
                'addressLocality' => $lb['addressLocality'] ?? null,
                'openingHours' => $lb['openingHours'] ?? null,
            ]);
        }

        $seoPayload = $seo->mergeSocialTags([
            'title' => $seoTitle,
            'description' => $description,
            'canonical' => $canonical,
            'og_title' => $seoTitle,
            'og_description' => $description,
            'og_type' => 'website',
            'schema_json' => $schemas !== [] ? $seo->encodeSchemas($schemas) : null,
        ], $marketing?->default_og_image_url, $marketing?->twitter_site);

        return Inertia::render('Store/StaticPage', [
            'slug' => $slug,
            'title' => $title,
            'body' => (string) $body,
            'seo' => $seoPayload,
        ]);
    }

    /**
     * @param  list<array{q: string, a: string}>  $faqs
     */
    private function faqBodyFromConfig(array $faqs): string
    {
        $html = '<p>Quick answers about shopping football boots, cleats, grippers, and accessories in Pakistan.</p>';
        foreach ($faqs as $faq) {
            $q = htmlspecialchars((string) ($faq['q'] ?? ''), ENT_QUOTES, 'UTF-8');
            $a = htmlspecialchars((string) ($faq['a'] ?? ''), ENT_QUOTES, 'UTF-8');
            $html .= "<h2>{$q}</h2><p>{$a}</p>";
        }

        return $html;
    }
}
