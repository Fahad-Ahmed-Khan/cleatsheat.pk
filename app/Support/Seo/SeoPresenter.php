<?php

namespace App\Support\Seo;

use App\Support\Storage\PublicAssetUrl;
use App\Models\Category;
use App\Models\ContentPost;
use App\Models\Product;
use Illuminate\Support\Str;

final class SeoPresenter
{
    public function absoluteUrl(?string $url): ?string
    {
        return PublicAssetUrl::resolve($url);
    }

    public function canonicalHome(): string
    {
        return rtrim(config('app.url'), '/').'/';
    }

    public function canonicalCategory(string $slug): string
    {
        return rtrim(config('app.url'), '/').'/c/'.$slug;
    }

    public function canonicalProduct(Product $product): string
    {
        if (filled($product->canonical_url)) {
            return (string) $product->canonical_url;
        }

        return rtrim(config('app.url'), '/').'/p/'.$product->slug;
    }

    public function canonicalJournalIndex(): string
    {
        return rtrim(config('app.url'), '/').'/journal';
    }

    public function canonicalJournalPost(ContentPost $post): string
    {
        return rtrim(config('app.url'), '/').'/journal/'.$post->slug;
    }

    /**
     * Public store name used across titles and structured data.
     */
    public function storeName(): string
    {
        return (string) (config('app.name') ?: 'Store');
    }

    /**
     * Geo-targeted category title: "{Category} in Pakistan | Buy Online at {Store}".
     * Honours an admin-provided meta_title when set.
     */
    public function categoryTitle(Category $category): string
    {
        if (filled($category->meta_title)) {
            return (string) $category->meta_title;
        }

        return $category->name.' in Pakistan | Buy Online at '.$this->storeName();
    }

    /**
     * Geo-targeted product title: "{Product} - {Brand} {Type} | {Store} Pakistan".
     * Honours an admin-provided meta_title when set.
     */
    public function productTitle(Product $product): string
    {
        if (filled($product->meta_title)) {
            return (string) $product->meta_title;
        }

        $brand = $product->brand?->name;
        $type = $product->shoe_type
            ? Str::headline(str_replace('_', ' ', $product->shoe_type->value))
            : null;

        $descriptor = trim(implode(' ', array_filter([$brand, $type ?: 'Football Shoes'])));

        return $product->name.' - '.$descriptor.' | '.$this->storeName().' Pakistan';
    }

    /**
     * Rich plain-text description for meta / OG (shoe-focused).
     */
    public function productDescriptionForSeo(Product $product): string
    {
        if (filled($product->meta_description)) {
            return Str::limit(strip_tags((string) $product->meta_description), 320, '');
        }

        $parts = [];
        $gender = $product->gender?->value;
        $type = $product->shoe_type?->value;
        if ($gender && $type) {
            $parts[] = Str::headline($gender).' '.Str::headline(str_replace('_', ' ', $type)).' shoes — '.$product->name.'.';
        } else {
            $parts[] = $product->name.' — premium footwear in Pakistan.';
        }
        if (filled($product->fit_notes)) {
            $parts[] = 'Fit: '.Str::limit(strip_tags((string) $product->fit_notes), 120, '…');
        }
        if (filled($product->size_info)) {
            $parts[] = 'Sizing: '.Str::limit(strip_tags((string) $product->size_info), 120, '…');
        }
        $parts[] = Str::limit(strip_tags((string) $product->description), 200, '…');

        return Str::limit(implode(' ', $parts), 320, '…');
    }

    /**
     * Category meta fallback tuned for common shoe search intents.
     */
    public function categoryIntentDescription(Category $category): string
    {
        if (filled($category->meta_description)) {
            return Str::limit((string) $category->meta_description, 320, '');
        }

        $slug = strtolower((string) $category->slug);
        $name = $category->name;

        // Football-first intents (highest commercial priority for this store).
        $footballHints = [
            'gripper' => "Buy football grippers in Pakistan — anti-slip grip socks and gripper pads for better lock-in inside your boots. UK/EU sizing, COD and fast nationwide delivery.",
            'sock' => "Shop football socks in Pakistan — grip socks, anti-slip and long match socks for FG, AG & turf play. Breathable, durable, with COD and fast delivery.",
            'cleat' => "Buy football cleats in Pakistan — FG, SG, AG & turf cleats with UK/EU/PK sizing, fit notes and WhatsApp help. COD and fast nationwide delivery.",
            'football-shoe' => "Buy football shoes online in Pakistan — FG, SG, AG & turf boots with UK/EU/PK sizing, inspected condition and fit help. COD and fast delivery from Lahore to Karachi.",
            'football-boot' => "Shop football boots in Pakistan — firm ground, soft ground, AG and turf studs with clear UK/EU sizing. COD and fast nationwide delivery.",
            'accessor' => "Football accessories in Pakistan — socks, grippers, laces, insoles, bags and care kits for match-ready players. COD and fast nationwide delivery.",
            'football' => "Shop {$name} in Pakistan — surface-matched football gear with UK/EU/PK sizing, fit help and COD nationwide delivery.",
            'futsal' => "Buy futsal & indoor shoes in Pakistan — flat-sole IC boots for halls and concrete courts. UK/EU sizing, COD and fast delivery.",
        ];

        foreach ($footballHints as $needle => $text) {
            if (str_contains($slug, $needle) || str_contains(strtolower($name), str_replace('-', ' ', $needle))) {
                return Str::limit($text, 320, '');
            }
        }

        $hints = [
            'women' => "Shop women's {$name} in Pakistan — comfortable daily wear and statement pairs with clear sizing.",
            'men' => "Shop men's {$name} in Pakistan — office-ready and casual options with UK / EU / PK sizing and fast delivery.",
            'running' => "Running shoes and {$name} — cushioned trainers for roads and tracks, with fit notes and size guides.",
            'formal' => "Formal {$name} for work and events — leather-look and classic silhouettes with dependable sizing.",
            'sneaker' => "Sneakers & {$name} — lifestyle and athletic styles for men and women, authentic pairs shipped nationwide.",
            'sport' => "Sports {$name} — supportive footwear for training and everyday movement, sized for Pakistani shoppers.",
        ];

        foreach ($hints as $needle => $text) {
            if ($needle === 'men' && str_contains($slug, 'women')) {
                continue;
            }
            if (str_contains($slug, $needle)) {
                return Str::limit($text, 320, '');
            }
        }

        return Str::limit("Browse {$name} online in Pakistan — curated styles, transparent PKR pricing, UK/EU/PK size charts and COD delivery.", 320, '');
    }

    /**
     * @return array<string, mixed>
     */
    public function productJsonLd(Product $product, string $canonicalUrl): array
    {
        $images = $product->images
            ->map(fn ($img) => $this->absoluteUrl($img->path))
            ->filter()
            ->values()
            ->all();

        $offers = [];
        foreach ($product->variants as $v) {
            if (! $v->is_active) {
                continue;
            }
            $offers[] = [
                '@type' => 'Offer',
                'sku' => $v->sku,
                'priceCurrency' => 'PKR',
                'price' => (string) $v->price,
                'availability' => 'https://schema.org/InStock',
                'url' => $canonicalUrl,
            ];
        }

        if ($offers === []) {
            $offers[] = [
                '@type' => 'Offer',
                'priceCurrency' => 'PKR',
                'price' => null,
                'availability' => 'https://schema.org/InStock',
                'url' => $canonicalUrl,
            ];
        }

        $additional = [];
        if ($product->gender) {
            $additional[] = [
                '@type' => 'PropertyValue',
                'name' => 'Gender',
                'value' => Str::headline($product->gender->value),
            ];
        }
        if ($product->shoe_type) {
            $additional[] = [
                '@type' => 'PropertyValue',
                'name' => 'Shoe type',
                'value' => Str::headline($product->shoe_type->value),
            ];
        }
        if (filled($product->fit_notes)) {
            $additional[] = [
                '@type' => 'PropertyValue',
                'name' => 'Fit notes',
                'value' => strip_tags((string) $product->fit_notes),
            ];
        }
        if (filled($product->size_info)) {
            $additional[] = [
                '@type' => 'PropertyValue',
                'name' => 'Size information',
                'value' => strip_tags((string) $product->size_info),
            ];
        }

        $desc = $this->productDescriptionForSeo($product);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => $desc,
            'sku' => $product->variants->first()?->sku,
            'brand' => [
                '@type' => 'Brand',
                'name' => $product->brand->name,
            ],
            'image' => $images,
            'offers' => $offers,
        ];

        if ($additional !== []) {
            $schema['additionalProperty'] = $additional;
        }

        return $schema;
    }

    /**
     * @param  list<array{slug: string, name: string}>  $products
     * @param  array{telephone?: ?string, email?: ?string, logo?: ?string, sameAs?: list<?string>, streetAddress?: ?string, addressLocality?: ?string}  $contact
     * @return array<string, mixed>
     */
    public function homeJsonLd(string $siteName, string $canonicalUrl, array $products, array $contact = []): array
    {
        $items = [];
        foreach (array_slice($products, 0, 12) as $i => $p) {
            $slug = $p['slug'] ?? null;
            if (! $slug) {
                continue;
            }
            $items[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => rtrim(config('app.url'), '/').'/p/'.$slug,
                'name' => $p['name'] ?? '',
            ];
        }

        $organization = [
            '@type' => 'Organization',
            'name' => $siteName,
            'url' => $canonicalUrl,
            'areaServed' => 'PK',
        ];

        if (filled($contact['logo'] ?? null)) {
            $organization['logo'] = $this->absoluteUrl($contact['logo']);
        }
        if (filled($contact['telephone'] ?? null)) {
            $organization['telephone'] = $contact['telephone'];
            $organization['contactPoint'] = [
                '@type' => 'ContactPoint',
                'telephone' => $contact['telephone'],
                'contactType' => 'customer service',
                'areaServed' => 'PK',
                'availableLanguage' => ['en', 'ur'],
            ];
        }
        if (filled($contact['email'] ?? null)) {
            $organization['email'] = $contact['email'];
        }

        $address = array_filter([
            'streetAddress' => $contact['streetAddress'] ?? null,
            'addressLocality' => $contact['addressLocality'] ?? null,
        ], fn ($v) => filled($v));
        if ($address !== []) {
            $organization['address'] = array_merge([
                '@type' => 'PostalAddress',
                'addressCountry' => 'PK',
            ], $address);
        }

        $sameAs = array_values(array_filter($contact['sameAs'] ?? [], fn ($v) => filled($v)));
        if ($sameAs !== []) {
            $organization['sameAs'] = $sameAs;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebSite',
                    'name' => $siteName,
                    'url' => $canonicalUrl,
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => rtrim(config('app.url'), '/').'/shop?q={search_term_string}',
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
                $organization,
                [
                    '@type' => 'ItemList',
                    'name' => 'Featured football boots',
                    'itemListElement' => $items,
                ],
            ],
        ];
    }

    /**
     * BreadcrumbList JSON-LD from ordered crumbs.
     *
     * @param  list<array{name: string, url: string}>  $crumbs
     * @return array<string, mixed>
     */
    public function breadcrumbJsonLd(array $crumbs): array
    {
        $items = [];
        foreach (array_values($crumbs) as $i => $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * CollectionPage + ItemList JSON-LD for category / shop listings.
     *
     * @param  list<array<string, mixed>>  $products
     * @return array<string, mixed>
     */
    public function collectionJsonLd(string $name, string $description, string $canonicalUrl, array $products): array
    {
        $items = [];
        foreach (array_slice($products, 0, 24) as $i => $p) {
            $slug = $p['slug'] ?? null;
            if (! $slug) {
                continue;
            }
            $items[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => rtrim(config('app.url'), '/').'/p/'.$slug,
                'name' => $p['name'] ?? '',
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $name,
            'description' => $description,
            'url' => $canonicalUrl,
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => $items,
            ],
        ];
    }

    /**
     * BlogPosting JSON-LD for journal articles.
     *
     * @return array<string, mixed>
     */
    public function articleJsonLd(ContentPost $post, string $canonicalUrl, ?string $image = null): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post->meta_title ?: $post->title,
            'description' => $post->meta_description ?? (string) ($post->excerpt ?? ''),
            'url' => $canonicalUrl,
            'mainEntityOfPage' => $canonicalUrl,
            'author' => ['@type' => 'Organization', 'name' => $this->storeName()],
            'publisher' => ['@type' => 'Organization', 'name' => $this->storeName()],
        ];

        if ($post->published_at) {
            $schema['datePublished'] = $post->published_at->toIso8601String();
        }
        if ($post->updated_at) {
            $schema['dateModified'] = $post->updated_at->toIso8601String();
        }
        if (filled($image)) {
            $schema['image'] = $this->absoluteUrl($image);
        }

        return $schema;
    }

    /**
     * FAQPage JSON-LD.
     *
     * @param  list<array{q: string, a: string}>  $faqs
     * @return array<string, mixed>
     */
    public function faqJsonLd(array $faqs): array
    {
        $items = [];
        foreach ($faqs as $faq) {
            $items[] = [
                '@type' => 'Question',
                'name' => $faq['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['a'],
                ],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $items,
        ];
    }

    /**
     * LocalBusiness / Store JSON-LD for the contact page.
     *
     * @param  array{name?: string, url?: string, telephone?: ?string, email?: ?string, streetAddress?: ?string, addressLocality?: ?string, openingHours?: ?string}  $business
     * @return array<string, mixed>
     */
    public function localBusinessJsonLd(array $business): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'SportingGoodsStore',
            'name' => $business['name'] ?? $this->storeName(),
            'url' => $business['url'] ?? $this->canonicalHome(),
            'areaServed' => 'PK',
            'priceRange' => 'PKR',
            'currenciesAccepted' => 'PKR',
        ];

        if (filled($business['telephone'] ?? null)) {
            $schema['telephone'] = $business['telephone'];
        }
        if (filled($business['email'] ?? null)) {
            $schema['email'] = $business['email'];
        }

        $address = array_filter([
            'streetAddress' => $business['streetAddress'] ?? null,
            'addressLocality' => $business['addressLocality'] ?? null,
        ], fn ($v) => filled($v));
        if ($address !== []) {
            $schema['address'] = array_merge([
                '@type' => 'PostalAddress',
                'addressCountry' => 'PK',
            ], $address);
        }

        if (filled($business['openingHours'] ?? null)) {
            $schema['openingHours'] = $business['openingHours'];
        }

        return $schema;
    }

    /**
     * Encode one or more schema graphs into a single JSON-LD payload.
     * Multiple schemas are emitted as a JSON array (valid JSON-LD).
     *
     * @param  list<array<string, mixed>>  $schemas
     */
    public function encodeSchemas(array $schemas): ?string
    {
        $schemas = array_values(array_filter($schemas, fn ($s) => is_array($s) && $s !== []));
        if ($schemas === []) {
            return null;
        }

        $payload = count($schemas) === 1 ? $schemas[0] : $schemas;

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function mergeSocialTags(array $base, ?string $defaultOgImage, ?string $twitterSite): array
    {
        $ogImage = $this->absoluteUrl($base['og_image'] ?? null)
            ?? $this->absoluteUrl($defaultOgImage);

        $base['og_image'] = $ogImage;
        $base['og_title'] = $base['og_title'] ?? $base['title'] ?? null;
        $base['og_description'] = $base['og_description'] ?? $base['description'] ?? null;
        $base['og_site_name'] = $base['og_site_name'] ?? ($this->storeName() ?: null);
        $base['og_locale'] = $base['og_locale'] ?? 'en_PK';
        $base['twitter_card'] = $base['twitter_card'] ?? 'summary_large_image';
        $base['twitter_site'] = $twitterSite;

        return $base;
    }
}
