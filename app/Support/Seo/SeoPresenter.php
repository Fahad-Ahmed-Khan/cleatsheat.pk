<?php

namespace App\Support\Seo;

use App\Models\Category;
use App\Models\ContentPost;
use App\Models\Product;
use Illuminate\Support\Str;

final class SeoPresenter
{
    public function absoluteUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return rtrim(config('app.url'), '/').'/'.ltrim($url, '/');
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

        return Str::limit("Browse {$name} shoes online in Pakistan — curated styles, transparent pricing, and UK/EU/PK size charts.", 320, '');
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
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public function mergeSocialTags(array $base, ?string $defaultOgImage, ?string $twitterSite): array
    {
        $ogImage = $this->absoluteUrl($base['og_image'] ?? null)
            ?? $this->absoluteUrl($defaultOgImage);

        $base['og_image'] = $ogImage;
        $base['og_title'] = $base['og_title'] ?? $base['title'] ?? null;
        $base['og_description'] = $base['og_description'] ?? $base['description'] ?? null;
        $base['twitter_card'] = $base['twitter_card'] ?? 'summary_large_image';
        $base['twitter_site'] = $twitterSite;

        return $base;
    }
}
