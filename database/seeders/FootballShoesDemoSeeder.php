<?php

namespace Database\Seeders;

use App\Enums\FitGuidance;
use App\Enums\Gender;
use App\Enums\ShoeType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\VariantSize;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class FootballShoesDemoSeeder extends Seeder
{
    public const CATEGORY_PARENT_SLUG = 'football-shoes';

    /**
     * @var list<array{name: string, slug: string}>
     */
    public const BRANDS = [
        ['name' => 'Nike', 'slug' => 'nike'],
        ['name' => 'Adidas', 'slug' => 'adidas'],
        ['name' => 'Puma', 'slug' => 'puma'],
        ['name' => 'New Balance', 'slug' => 'new-balance'],
        ['name' => 'Mizuno', 'slug' => 'mizuno'],
        ['name' => 'Under Armour', 'slug' => 'under-armour'],
        ['name' => 'Diadora', 'slug' => 'diadora'],
        ['name' => 'Joma', 'slug' => 'joma'],
        ['name' => 'Umbro', 'slug' => 'umbro'],
        ['name' => 'Lotto', 'slug' => 'lotto'],
    ];

    /**
     * @var list<array{name: string, slug: string}>
     */
    public const SURFACE_CATEGORIES = [
        ['name' => 'Firm Ground (FG)', 'slug' => 'football-fg'],
        ['name' => 'Soft Ground (SG)', 'slug' => 'football-sg'],
        ['name' => 'Artificial Grass (AG)', 'slug' => 'football-ag'],
        ['name' => 'Turf (TF)', 'slug' => 'football-tf'],
        ['name' => 'Indoor / Futsal (IC)', 'slug' => 'football-ic'],
    ];

    public function run(): void
    {
        $store = config('app.name', 'CleatSheat.pk');
        $parent = Category::query()->firstOrCreate(
            ['slug' => self::CATEGORY_PARENT_SLUG],
            [
                'parent_id' => null,
                'name' => 'Football Shoes',
                'meta_title' => "Football Shoes in Pakistan | Buy Online at {$store}",
                'meta_description' => 'Buy football shoes & boots online in Pakistan — FG, SG, AG & turf with UK/EU sizing, inspected condition, COD and fast delivery.',
                'intro_html' => '<p>Shop <strong>football shoes</strong> for every surface in Pakistan — FG, SG, AG, turf & indoor. Clear UK/EU sizing, WhatsApp fit help, and COD nationwide.</p>',
                'sort_order' => 10,
                'is_active' => true,
            ]
        );

        $surfaceCategories = [];
        foreach (self::SURFACE_CATEGORIES as $i => $row) {
            $surfaceCategories[] = Category::query()->firstOrCreate(
                ['slug' => $row['slug']],
                [
                    'parent_id' => $parent->id,
                    'name' => $row['name'],
                    'meta_title' => $row['name'],
                    'meta_description' => 'Football shoes for '.$row['name'].'.',
                    'sort_order' => $i,
                ]
            );
        }

        $brands = [];
        foreach (self::BRANDS as $row) {
            $brands[] = Brand::query()->firstOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'meta_title' => $row['name'].' Football Shoes',
                    'meta_description' => 'Demo catalog for '.$row['name'].' football boots.',
                ]
            );
        }

        // Ensure we have a basic palette for variants.
        $colors = [
            Color::query()->firstOrCreate(['slug' => 'black'], ['name' => 'Black', 'hex' => '#111111']),
            Color::query()->firstOrCreate(['slug' => 'white'], ['name' => 'White', 'hex' => '#f5f5f5']),
            Color::query()->firstOrCreate(['slug' => 'red'], ['name' => 'Red', 'hex' => '#ef4444']),
            Color::query()->firstOrCreate(['slug' => 'blue'], ['name' => 'Blue', 'hex' => '#3b82f6']),
            Color::query()->firstOrCreate(['slug' => 'lime'], ['name' => 'Lime', 'hex' => '#84cc16']),
        ];

        $lines = [
            'Strike',
            'Speed',
            'Control',
            'Phantom',
            'Predator',
            'Future',
            'Ultra',
            'Tekela',
            'Morelia',
            'King',
        ];

        $highlights = [
            'Textured upper for improved touch',
            'Lightweight plate for fast acceleration',
            'Anti-clog traction pattern',
            'Responsive foam insole',
            'Reinforced toe for durability',
            'Breathable knit collar fit',
        ];

        $sizes = [
            ['UK 6', '6', '40', '6'],
            ['UK 7', '7', '41', '7'],
            ['UK 8', '8', '42', '8'],
            ['UK 9', '9', '43', '9'],
            ['UK 10', '10', '44', '10'],
            ['UK 11', '11', '45', '11'],
        ];

        for ($i = 1; $i <= 50; $i++) {
            /** @var Brand $brand */
            $brand = Arr::random($brands);
            /** @var Category $cat */
            $cat = Arr::random($surfaceCategories);

            $gender = Arr::random([Gender::Men, Gender::Unisex, Gender::Kids]);
            $fit = Arr::random([FitGuidance::TrueToSize, FitGuidance::RunsSmall, FitGuidance::RunsLarge]);

            $surfaceShort = Str::of($cat->name)->match('/\(([^)]+)\)/')->toString();
            $lineName = Arr::random($lines);
            $model = $lineName.' '.$surfaceShort.' Pro '.$i;

            $name = $brand->name.' '.$model;
            $slug = Str::slug($brand->slug.'-'.$model);

            $product = Product::query()->create([
                'brand_id' => $brand->id,
                'category_id' => $cat->id,
                'size_chart_id' => null,
                'name' => $name,
                'slug' => $slug,
                'description' => '<p>Demo football shoe for '.$cat->name.'. Built for match-day speed and all-day comfort.</p>',
                'meta_title' => $name,
                'meta_description' => 'Football shoes for '.$cat->name.' — '.$brand->name.'.',
                'canonical_url' => null,
                'fit_guidance' => $fit,
                'gender' => $gender,
                'shoe_type' => ShoeType::Athletic,
                'fit_notes' => $fit === FitGuidance::RunsSmall ? 'Runs snug — consider sizing up for wide feet.' : null,
                'size_info' => 'UK sizing with EU reference. If between sizes, size up for a roomier fit.',
                'features' => Arr::random($highlights, 4),
                'is_active' => true,
            ]);

            $text = rawurlencode($brand->name.' '.$surfaceShort);
            ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => "https://placehold.co/800x1000/0b1220/e2e8f0?text={$text}",
                'alt' => $name.' — hero',
                'sort_order' => 0,
            ]);
            ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => "https://placehold.co/800x1000/111827/f8fafc?text={$text}+2",
                'alt' => $name.' — detail',
                'sort_order' => 1,
            ]);

            // Create 1–2 color variants per product.
            $variantCount = $i % 3 === 0 ? 2 : 1;
            $pickedColors = Arr::random($colors, $variantCount);
            if (! is_array($pickedColors)) {
                $pickedColors = [$pickedColors];
            }

            foreach (array_values($pickedColors) as $vi => $color) {
                $sku = strtoupper(Str::of($brand->slug)->substr(0, 3)).'-FB-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT).'-'.str_pad((string) ($vi + 1), 2, '0', STR_PAD_LEFT);
                $price = 8999 + ($i * 50);
                $maxDiscountPercent = (float) Arr::random([10, 12.5, 15, 17.5, 20, 22.5, 25]);
                $minPrice = (float) round($price * (1 - ($maxDiscountPercent / 100)), 2);

                $variant = ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'color_id' => $color->id,
                    'sku' => $sku,
                    'price' => $price,
                    'compare_at_price' => $i % 4 === 0 ? ($price + 2000) : null,
                    'bargain_enabled' => true,
                    'bargain_min_price' => $minPrice,
                    'bargain_max_discount_percent' => $maxDiscountPercent,
                    'is_active' => true,
                ]);

                foreach ($sizes as $si => $s) {
                    VariantSize::query()->create([
                        'product_variant_id' => $variant->id,
                        'size_label' => $s[0],
                        'uk_size' => $s[1],
                        'eu_size' => $s[2],
                        'pk_size' => $s[3],
                        'stock_qty' => max(0, 18 - ($si * 3) - ($i % 5)),
                        'low_stock_threshold' => 3,
                    ]);
                }
            }
        }
    }
}

