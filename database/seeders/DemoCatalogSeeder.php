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
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\SizeChart;
use App\Models\SizeChartRow;
use App\Models\VariantSize;
use Illuminate\Database\Seeder;

class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $brand = Brand::query()->create([
            'name' => 'Tryino Originals',
            'slug' => 'tryino-originals',
            'meta_title' => 'Tryino Originals',
            'meta_description' => 'House brand sneakers and boots.',
        ]);

        $category = Category::query()->create([
            'parent_id' => null,
            'name' => 'Sneakers',
            'slug' => 'sneakers',
            'sort_order' => 0,
        ]);

        $colorBlack = Color::query()->create([
            'name' => 'Black',
            'slug' => 'black',
            'hex' => '#111111',
        ]);

        $colorWhite = Color::query()->create([
            'name' => 'White',
            'slug' => 'white',
            'hex' => '#f5f5f5',
        ]);

        $chart = SizeChart::query()->create([
            'brand_id' => $brand->id,
            'name' => 'Men sneakers (UK / EU / PK)',
            'gender' => Gender::Men,
            'shoe_type' => ShoeType::Sneaker,
        ]);

        $chartRows = [
            ['UK 7', '7', '41', '7', 25.5],
            ['UK 8', '8', '42', '8', 26.5],
            ['UK 9', '9', '43', '9', 27.5],
            ['UK 10', '10', '44', '10', 28.5],
        ];

        foreach ($chartRows as $i => $row) {
            SizeChartRow::query()->create([
                'size_chart_id' => $chart->id,
                'sort_order' => $i,
                'label' => $row[0],
                'uk_size' => $row[1],
                'eu_size' => $row[2],
                'pk_size' => $row[3],
                'foot_cm' => $row[4],
                'measurements' => ['cm' => $row[4]],
            ]);
        }

        $product = Product::query()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'size_chart_id' => $chart->id,
            'name' => 'Urban Runner Pro',
            'slug' => 'urban-runner-pro',
            'description' => '<p>Lightweight daily trainer with breathable mesh and cushioned sole. Designed for Pakistan streets and gym sessions.</p>',
            'meta_title' => 'Urban Runner Pro — Tryino',
            'meta_description' => 'Premium sneakers with responsive cushioning and breathable mesh.',
            'fit_guidance' => FitGuidance::TrueToSize,
            'gender' => Gender::Men,
            'shoe_type' => ShoeType::Sneaker,
            'fit_notes' => 'Wide feet may prefer half size up.',
            'features' => [
                'Breathable engineered mesh upper',
                'Responsive EVA midsole',
                'Durable rubber outsole',
                'Padded collar for ankle comfort',
            ],
            'is_active' => true,
        ]);

        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'https://placehold.co/800x1000/f4f4f5/18181b?text=Urban+Runner',
            'alt' => 'Urban Runner Pro — hero',
            'sort_order' => 0,
        ]);

        $sizes = [
            ['UK 7', '7', '41', '7'],
            ['UK 8', '8', '42', '8'],
            ['UK 9', '9', '43', '9'],
            ['UK 10', '10', '44', '10'],
        ];

        $variantBlack = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color_id' => $colorBlack->id,
            'sku' => 'URB-BLK-001',
            'price' => 12999,
            'compare_at_price' => 15999,
            'is_active' => true,
            'bargain_enabled' => true,
            'bargain_min_price' => 11000,
            'bargain_max_discount_percent' => 25,
        ]);

        foreach ($sizes as $s) {
            VariantSize::query()->create([
                'product_variant_id' => $variantBlack->id,
                'size_label' => $s[0],
                'uk_size' => $s[1],
                'eu_size' => $s[2],
                'pk_size' => $s[3],
                'stock_qty' => 15,
                'low_stock_threshold' => 3,
            ]);
        }

        $variantWhite = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color_id' => $colorWhite->id,
            'sku' => 'URB-WHT-001',
            'price' => 12999,
            'compare_at_price' => null,
            'is_active' => true,
        ]);

        foreach ($sizes as $s) {
            VariantSize::query()->create([
                'product_variant_id' => $variantWhite->id,
                'size_label' => $s[0],
                'uk_size' => $s[1],
                'eu_size' => $s[2],
                'pk_size' => $s[3],
                'stock_qty' => $s[0] === 'UK 10' ? 0 : 8,
                'low_stock_threshold' => 2,
            ]);
        }

        foreach (
            [
                ['author_display' => 'Ahmed K.', 'rating' => 5, 'fit_feedback' => 'true_to_size', 'title' => 'Daily driver', 'body' => 'True to size for me. Comfortable on Karachi concrete all day.'],
                ['author_display' => 'Sara M.', 'rating' => 4, 'fit_feedback' => 'runs_small', 'title' => 'Go half up if wide', 'body' => 'Narrow fit — I sized up and they are perfect now.'],
                ['author_display' => 'Bilal R.', 'rating' => 5, 'fit_feedback' => 'runs_large', 'title' => 'Lightweight', 'body' => 'Feels a touch roomy in UK 9; still happy with the purchase.'],
            ] as $rev
        ) {
            ProductReview::query()->create([
                'product_id' => $product->id,
                'author_display' => $rev['author_display'],
                'rating' => $rev['rating'],
                'fit_feedback' => $rev['fit_feedback'],
                'title' => $rev['title'],
                'body' => $rev['body'],
            ]);
        }
    }
}
