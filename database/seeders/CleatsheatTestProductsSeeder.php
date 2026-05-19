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
use Illuminate\Support\Facades\DB;

class CleatsheatTestProductsSeeder extends Seeder
{
    public function run(): void
    {
        $parent = Category::query()->firstOrCreate(
            ['slug' => FootballShoesDemoSeeder::CATEGORY_PARENT_SLUG],
            [
                'parent_id' => null,
                'name' => 'Football Shoes',
                'meta_title' => 'Football Shoes',
                'meta_description' => 'Football boots and futsal shoes for every surface.',
                'sort_order' => 10,
            ]
        );

        foreach (FootballShoesDemoSeeder::SURFACE_CATEGORIES as $i => $row) {
            Category::query()->firstOrCreate(
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

        foreach (FootballShoesDemoSeeder::BRANDS as $row) {
            Brand::query()->firstOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'meta_title' => $row['name'].' Football Shoes',
                    'meta_description' => 'Football boots by '.$row['name'].'.',
                ]
            );
        }

        $extraCategories = [
            ['name' => 'Premium Cleats', 'slug' => 'premium-cleats'],
            ['name' => 'Pro Cleats', 'slug' => 'pro-cleats'],
            ['name' => 'Budget Cleats', 'slug' => 'budget-cleats'],
            ['name' => 'Leather Cleats', 'slug' => 'leather-cleats'],
            ['name' => 'Kids Cleats', 'slug' => 'kids-cleats'],
            ['name' => 'New Arrivals', 'slug' => 'new-arrivals'],
        ];

        $categoryIds = [];
        foreach (FootballShoesDemoSeeder::SURFACE_CATEGORIES as $row) {
            $categoryIds[$row['slug']] = (int) Category::query()->where('slug', $row['slug'])->value('id');
        }
        foreach ($extraCategories as $i => $row) {
            $cat = Category::query()->firstOrCreate(
                ['slug' => $row['slug']],
                [
                    'parent_id' => $parent->id,
                    'name' => $row['name'],
                    'meta_title' => $row['name'],
                    'meta_description' => $row['name'].' football boots.',
                    'sort_order' => 20 + $i,
                ]
            );
            $categoryIds[$row['slug']] = (int) $cat->id;
        }

        $brandIds = [];
        foreach (['nike', 'adidas'] as $slug) {
            $brandIds[$slug] = (int) Brand::query()->where('slug', $slug)->value('id');
        }

        $colorId = (int) Color::query()->firstOrCreate(
            ['slug' => 'black'],
            ['name' => 'Black', 'hex' => '#111111']
        )->id;

        $categoryMap = [
            'Firm Ground' => 'football-fg',
            'Soft Ground' => 'football-sg',
            'Premium Cleats' => 'premium-cleats',
            'Pro Cleats' => 'pro-cleats',
            'Budget Cleats' => 'budget-cleats',
            'Leather Cleats' => 'leather-cleats',
            'Kids Cleats' => 'kids-cleats',
            'New Arrivals' => 'new-arrivals',
            'Turf Shoes' => 'football-tf',
        ];

        foreach (self::products() as $row) {
            DB::transaction(function () use ($row, $categoryIds, $brandIds, $colorId, $categoryMap) {
                $firstCategory = trim(explode(',', $row['categories'])[0]);
                $categorySlug = $categoryMap[$firstCategory] ?? 'football-fg';
                $categoryId = $categoryIds[$categorySlug] ?? reset($categoryIds);

                $product = Product::query()->updateOrCreate(
                    ['slug' => $row['slug']],
                    [
                        'brand_id' => $brandIds[$row['brand']],
                        'category_id' => $categoryId,
                        'size_chart_id' => null,
                        'name' => $row['name'],
                        'description' => $row['description'],
                        'meta_title' => $row['name'],
                        'meta_description' => strip_tags($row['description']),
                        'canonical_url' => $row['canonical_url'],
                        'fit_guidance' => FitGuidance::TrueToSize,
                        'gender' => Gender::Men,
                        'shoe_type' => ShoeType::Athletic,
                        'fit_notes' => null,
                        'size_info' => 'Size: '.$row['size_label'],
                        'features' => [
                            'Second-hand, inspected & cleaned',
                            'Condition rated in listing',
                        ],
                        'is_active' => $row['is_active'],
                    ]
                );

                $product->images()->delete();
                foreach ($row['images'] as $i => $path) {
                    ProductImage::query()->create([
                        'product_id' => $product->id,
                        'path' => $path,
                        'alt' => $row['name'],
                        'sort_order' => $i,
                    ]);
                }

                $product->variants()->delete();

                $variant = ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'color_id' => $colorId,
                    'sku' => $row['sku'],
                    'price' => $row['price'],
                    'compare_at_price' => null,
                    'is_active' => true,
                    'bargain_enabled' => false,
                ]);

                VariantSize::query()->create([
                    'product_variant_id' => $variant->id,
                    'size_label' => $row['size_label'],
                    'uk_size' => $row['uk_size'],
                    'eu_size' => null,
                    'pk_size' => null,
                    'stock_qty' => $row['stock_qty'],
                    'low_stock_threshold' => 1,
                ]);
            });
        }
    }

    /**
     * @return list<array{
     *     name: string,
     *     slug: string,
     *     sku: string,
     *     brand: string,
     *     categories: string,
     *     is_active: bool,
     *     description: string,
     *     canonical_url: string,
     *     size_label: string,
     *     uk_size: string,
     *     price: float,
     *     stock_qty: int,
     *     images: list<string>
     * }>
     */
    private static function products(): array
    {
        return [
            [
                'name' => 'Nike Mercurial Victory VI DF SG Football Shoes — Used',
                'slug' => 'nike-mercurial-victory-vi-df-sg-football-shoes-used-1',
                'sku' => 'CS001',
                'brand' => 'nike',
                'categories' => 'Firm Ground,Premium Cleats,New Arrivals',
                'is_active' => true,
                'description' => '<p>Nike Mercurial Victory VI DF SG Football Shoes — Used. Condition: <strong>10/10</strong>. Size: <strong>UK 8, US 8.5, EU 42, 27 cm</strong>. Second-hand, inspected & cleaned.</p>',
                'canonical_url' => 'https://cleatsheat.pk/products/nike-mercurial-victory-vi-df-sg-football-shoes-used-1',
                'size_label' => 'UK 8',
                'uk_size' => '8',
                'price' => 10000,
                'stock_qty' => 1,
                'images' => [
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/1580e480-e9a4-4c52-9cec-985385edc8cf.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/704c6f73-d128-440c-a598-2fbf354a6fcb.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/ace61d14-52f3-4173-8ba4-6c3de6b94758.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/15bd7591-778b-4f1e-84ba-7ba25d006e93.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/36e652a4-ba66-44da-b447-1e70762af0d3.jpg',
                ],
            ],
            [
                'name' => 'Nike Bravata II (FG) Football Shoes — Used',
                'slug' => 'nike-bravata-ii-fg-football-shoes-used-1',
                'sku' => 'CS002',
                'brand' => 'nike',
                'categories' => 'Premium Cleats',
                'is_active' => true,
                'description' => '<p>Nike Bravata II (FG) Football Shoes — Used. Condition: <strong>10/10</strong>. Size: <strong>UK 10, US 10.5, EU 44.5, 29 cm</strong>. Second-hand, inspected & cleaned.</p>',
                'canonical_url' => 'https://cleatsheat.pk/products/nike-bravata-ii-fg-football-shoes-used-1',
                'size_label' => 'UK 10',
                'uk_size' => '10',
                'price' => 10000,
                'stock_qty' => 1,
                'images' => [
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/1532797d-930c-4f71-8f0c-844826ccdc55.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/9a19b06d-8410-40ff-b3b5-0129f694ff91.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/c08d5bd2-da97-4f89-88c5-609409468d49.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/6347c76c-269d-4593-b01c-139c17a26c87.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/d8fcab8a-0f16-4e0e-9fe8-a9526ab83da5.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/cfc3748a-1460-4f39-a70f-83091c1d48fa.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/9987f8d2-9ed1-48d9-ab25-7b6107869615.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/d8c8d349-1c4b-4749-a1ff-fbb728b7d73d.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/35dfa6d6-7e88-4f56-bf38-f9c23abaa94e.jpg',
                ],
            ],
            [
                'name' => 'Nike Tiempo Legend 9 Elite Football Shoes — Used',
                'slug' => 'nike-tiempo-legend-9-elite-football-shoes-used-1',
                'sku' => 'CS003',
                'brand' => 'nike',
                'categories' => 'Firm Ground,Premium Cleats,Leather Cleats',
                'is_active' => false,
                'description' => '<p>Nike Tiempo Legend 9 Elite Football Shoes — Used. Condition: <strong>10/10</strong>. Size: <strong>UK 8.5, US 9, EU 42.5, 27.5 cm</strong>. Second-hand, inspected & cleaned.</p>',
                'canonical_url' => 'https://cleatsheat.pk/products/nike-tiempo-legend-9-elite-football-shoes-used-1',
                'size_label' => 'UK 8.5',
                'uk_size' => '8.5',
                'price' => 9000,
                'stock_qty' => 1,
                'images' => [
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/096504e8-8360-4b29-bfd0-692bed21a928.jpg',
                ],
            ],
            [
                'name' => 'Adidas Predator Freak.1 FG Football Shoes — Used',
                'slug' => 'adidas-predator-freak1-fg-football-shoes-used-1',
                'sku' => 'CS004',
                'brand' => 'adidas',
                'categories' => 'Firm Ground,Pro Cleats,Premium Cleats,New Arrivals',
                'is_active' => true,
                'description' => '<p>Adidas Predator Freak.1 FG Football Shoes — Used. Condition: <strong>10/10</strong>. Size: <strong>UK 8.5, US 9, EU 42 2/3, 26.3 cm</strong>. Second-hand, inspected & cleaned.</p>',
                'canonical_url' => 'https://cleatsheat.pk/products/adidas-predator-freak1-fg-football-shoes-used-1',
                'size_label' => 'UK 8.5',
                'uk_size' => '8.5',
                'price' => 12000,
                'stock_qty' => 1,
                'images' => [
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/342756f3-fc15-4892-a8b8-198a4d900898.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/59511a2b-a3a1-4539-bff4-0dd1f32c29f3.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/9a1e213e-79a7-4769-8854-9f9f5b173335.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/d04ced8b-a44d-4de8-8e6d-80d630e1df66.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/c2fb0655-3975-4897-ac76-a8a73a88e3f2.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/ed87c820-44bd-4b54-bd59-942f59220c35.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/1be20fe2-0e9e-4269-9e45-c8ea643fb1b6.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/70ad4fc4-3935-4614-83f6-01e7326704d8.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/ad430e84-6868-4e3b-8a13-4127a8e11ed7.jpg',
                ],
            ],
            [
                'name' => 'Adidas Cusco Vintage Limited Edition Football Shoes — Used',
                'slug' => 'adidas-cusco-vintage-limited-edition-football-shoes-used-1',
                'sku' => 'CS006',
                'brand' => 'adidas',
                'categories' => 'Firm Ground,Premium Cleats',
                'is_active' => true,
                'description' => '<p>Adidas Cusco Vintage Limited Edition Football Shoes — Used. Condition: <strong>10/10</strong>. Size: <strong>UK 8.5, US 9, EU 42 2/3, 26.3 cm</strong>. Second-hand, inspected & cleaned.</p>',
                'canonical_url' => 'https://cleatsheat.pk/products/adidas-cusco-vintage-limited-edition-football-shoes-used-1',
                'size_label' => 'UK 8.5',
                'uk_size' => '8.5',
                'price' => 9000,
                'stock_qty' => 1,
                'images' => [
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/44db6179-6b17-49e5-a558-aa9198946a82.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/eedafaa0-565d-4451-9d08-16af5a91eef9.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/3fb892d9-78d8-4f64-8f85-ef9259cb79c6.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/bfc2652a-c54c-468c-bdf5-079cbf5e5a54.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/7a1437c0-48e3-44e9-9232-93273cfdf5e2.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/5a9b41d2-558d-440c-a8e4-d8bb0965086f.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/dcc42bca-78ca-45ed-9f59-21e28e65ece2.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/f4f02250-0c8a-4991-ab1c-fc4509d4c3f9.jpg',
                ],
            ],
            [
                'name' => 'Nike Vapor Edge Elite 360 Football Shoes — Used',
                'slug' => 'nike-vapor-edge-elite-360-football-shoes-used-1',
                'sku' => 'CS007',
                'brand' => 'nike',
                'categories' => 'Firm Ground,Premium Cleats',
                'is_active' => true,
                'description' => '<p>Nike Vapor Edge Elite 360 Football Shoes — Used. Condition: <strong>10/10</strong>. Size: <strong>UK 7.5, US 8, EU 41, 26.5 cm</strong>. Second-hand, inspected & cleaned.</p>',
                'canonical_url' => 'https://cleatsheat.pk/products/nike-vapor-edge-elite-360-football-shoes-used-1',
                'size_label' => 'UK 7.5',
                'uk_size' => '7.5',
                'price' => 9500,
                'stock_qty' => 1,
                'images' => [
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/ca008c83-10b4-486c-918b-cba3a4bf1f00.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/c3b03cda-6585-4f81-a50d-ed3da3b5ed4e.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/17d8616b-60f6-4128-9ca4-cc2ea5b7a508.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/399ee7ae-a4b0-4a45-86e4-50d8bcdfe6a4.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/eb499f52-f3df-4b92-a761-7a294762205e.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/f9495462-6bfd-400f-ba18-327a03e2e1e2.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/f6c3fef0-be53-41f3-bb1f-5f2e392a51f1.jpg',
                ],
            ],
            [
                'name' => 'Nike Tiempo Legend 9 Elite Football Shoes — Used',
                'slug' => 'nike-tiempo-legend-9-elite-football-shoes-used-3',
                'sku' => 'CS009',
                'brand' => 'nike',
                'categories' => 'Firm Ground,Pro Cleats,Budget Cleats,Leather Cleats',
                'is_active' => true,
                'description' => '<p>Nike Tiempo Legend 9 Elite Football Shoes — Used. Condition: <strong>9.5/10</strong>. Size: <strong>UK 9, US 10, EU 44, 28 cm</strong>. Second-hand, inspected & cleaned.</p>',
                'canonical_url' => 'https://cleatsheat.pk/products/nike-tiempo-legend-9-elite-football-shoes-used-3',
                'size_label' => 'UK 9',
                'uk_size' => '9',
                'price' => 8600,
                'stock_qty' => 1,
                'images' => [
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/645af8ee-cb39-4a99-99d6-f4742ed17d52.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/153550c4-5626-4f27-a3cf-4739e58c3947.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/13506468-5030-400d-af7b-8cb438fb1ecd.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/a4b04b84-8eea-4dff-8b96-7debc22a8a7b.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/72b189ba-4b28-4f52-b98e-6c5eb0965dad.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/cb5c1f28-01d5-40f9-add1-c2a06a6eb38f.jpg',
                ],
            ],
            [
                'name' => 'Nike Vapor Edge Elite 360 Football Shoes — Used',
                'slug' => 'nike-vapor-edge-elite-360-football-shoes-used-2',
                'sku' => 'CS010',
                'brand' => 'nike',
                'categories' => 'Firm Ground,Premium Cleats',
                'is_active' => true,
                'description' => '<p>Nike Vapor Edge Elite 360 Football Shoes — Used. Condition: <strong>10/10</strong>. Size: <strong>UK 6.5, US 7, EU 4, 25.5 cm</strong>. Second-hand, inspected & cleaned.</p>',
                'canonical_url' => 'https://cleatsheat.pk/products/nike-vapor-edge-elite-360-football-shoes-used-2',
                'size_label' => 'UK 6.5',
                'uk_size' => '6.5',
                'price' => 9000,
                'stock_qty' => 1,
                'images' => [
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/629ceab7-9849-489c-a57d-de8a00e68e9b.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/64b17f24-c3de-49a9-a895-1d0fec19b714.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/05d0413c-de9b-447e-bfe3-103ac1138ed3.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/608badf1-13a5-47d0-a741-c665f9907f1c.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/a01f6b85-53cc-4fa4-ab81-e204e8148504.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/dd2ce9b9-d2ce-445f-9575-b39e269c0299.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/e25f3125-6f7f-4624-ae33-726ae4738eb6.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/78ef7de5-405e-4bc3-99e9-15a7c7e0c8e2.jpg',
                ],
            ],
            [
                'name' => 'Adidas Freak 23 Inline Football Shoes — Used',
                'slug' => 'adidas-freak-23-inline-football-shoes-used-1',
                'sku' => 'CS011',
                'brand' => 'adidas',
                'categories' => 'Firm Ground,Premium Cleats',
                'is_active' => true,
                'description' => '<p>Adidas Freak 23 Inline Football Shoes — Used. Condition: <strong>10/10</strong>. Size: <strong>UK 6, US 6.5, EU 39 1/3, 24.2 cm</strong>. Second-hand, inspected & cleaned.</p>',
                'canonical_url' => 'https://cleatsheat.pk/products/adidas-freak-23-inline-football-shoes-used-1',
                'size_label' => 'UK 6',
                'uk_size' => '6',
                'price' => 9800,
                'stock_qty' => 1,
                'images' => [
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/95a9f5d1-32d1-44eb-8106-4ccacf11a216.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/6090e04d-ce0d-4add-b9d9-c6bd0bf6299f.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/b6c794a8-36a6-45af-bd38-d6e7d5c88692.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/113fa5f4-4774-47c3-b5cd-e64bc9a25825.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/a1fd6326-789b-481a-866b-93e5fed60b88.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/ed976f6c-730f-437d-856d-2bed24f88d57.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/312ed20e-3670-4469-9a2a-c0d1a1f12254.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/7c759a0f-a718-411b-8120-01de58fd3444.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/64b17f24-c3de-49a9-a895-1d0fec19b714.jpg',
                ],
            ],
            [
                'name' => 'Nike Vapor Football Shoes — Used',
                'slug' => 'nike-vapor-football-shoes-used-1',
                'sku' => 'CS012',
                'brand' => 'nike',
                'categories' => 'Firm Ground,Premium Cleats,Budget Cleats',
                'is_active' => true,
                'description' => '<p>Nike Vapor Football Shoes — Used. Condition: <strong>9.5/10</strong>. Size: <strong>UK 8, US 8.5, EU 42, 27 cm</strong>. Second-hand, inspected & cleaned.</p>',
                'canonical_url' => 'https://cleatsheat.pk/products/nike-vapor-football-shoes-used-1',
                'size_label' => 'UK 8',
                'uk_size' => '8',
                'price' => 8800,
                'stock_qty' => 1,
                'images' => [
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/e9ca55a6-e9cb-4b40-8f40-31618c7d469d.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/373e7786-cdbf-4f46-a5a9-693e0534ea82.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/66616c5a-f718-4b02-b546-40177a647326.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/849e18d7-6b3c-49d5-99ad-b7646a3e0099.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/96fbf7db-0b14-40b1-869d-102311023607.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/c3f32fc5-466e-491b-a3d8-9db98daa13d6.jpg',
                ],
            ],
            [
                'name' => 'Nike Hypervenom Football Shoes — Used',
                'slug' => 'nike-hypervenom-football-shoes-used-1',
                'sku' => 'CS013',
                'brand' => 'nike',
                'categories' => 'Firm Ground,Kids Cleats,Premium Cleats',
                'is_active' => true,
                'description' => '<p>Nike Hypervenom Football Shoes — Used. Condition: <strong>10/10</strong>. Size: <strong>UK 5.5, US 6, EU 38.5, 24.5 cm</strong>. Second-hand, inspected & cleaned.</p>',
                'canonical_url' => 'https://cleatsheat.pk/products/nike-hypervenom-football-shoes-used-1',
                'size_label' => 'UK 5.5',
                'uk_size' => '5.5',
                'price' => 7000,
                'stock_qty' => 1,
                'images' => [
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/b38cb294-6b05-466b-b082-e3ab1b882923.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/2b5e2d38-0d14-44dc-89c1-5cd4619fbfda.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/8aa49e70-7863-4ae9-9070-5ba0e129a33c.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/94c64da3-5669-4030-bf57-d5ef42130d05.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/582917fd-11bf-4fc5-ac70-72ecd1acd1ca.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/e606054d-dc26-49ff-9da5-9ebb40de4a4e.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/4f529dc2-ba3c-432a-90a7-cd0e4f79f1c7.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/40b10bd1-9a8c-4d55-80fa-0366983c41a9.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/c25ee12d-9643-4f3e-b984-32e77bc18171.jpg',
                ],
            ],
            [
                'name' => 'Nike Vapor 360 Pro Football Shoes — Used',
                'slug' => 'nike-vapor-360-pro-football-shoes-used-1',
                'sku' => 'CS014',
                'brand' => 'nike',
                'categories' => 'Firm Ground,Premium Cleats',
                'is_active' => true,
                'description' => '<p>Nike Vapor 360 Pro Football Shoes — Used. Condition: <strong>10/10</strong>. Size: <strong>UK 9, US 10, EU 44, 28 cm</strong>. Second-hand, inspected & cleaned.</p>',
                'canonical_url' => 'https://cleatsheat.pk/products/nike-vapor-360-pro-football-shoes-used-1',
                'size_label' => 'UK 9',
                'uk_size' => '9',
                'price' => 7800,
                'stock_qty' => 1,
                'images' => [
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/b4c3e400-d4f4-4a18-b372-eb90598b0e01.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/f4768ebe-ab9d-4660-a76f-0e86cec3362d.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/8a8d50e1-e44e-486d-baaf-e813670afea6.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/c4f681e1-7f3b-49db-9da3-b1df5d86c6b5.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/20f0cd63-072c-4392-8628-15c2288c57a8.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/717e5c3c-fb0d-44de-98a7-4065766669db.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/f0e5dd53-9eda-4a9f-9d2c-e22f2ea30aa5.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/47a58a41-832f-42d7-b9ec-befd0b16e17f.jpg',
                ],
            ],
            [
                'name' => 'Adidas Predator Football Shoes — Used',
                'slug' => 'adidas-predator-football-shoes-used-1',
                'sku' => 'CS015',
                'brand' => 'adidas',
                'categories' => 'Turf Shoes,Kids Cleats,Premium Cleats,Budget Cleats',
                'is_active' => true,
                'description' => '<p>Adidas Predator Football Shoes — Used. Condition: <strong>10/10</strong>. Size: <strong>UK 5, US 5.5, EU 38, 23.3 cm</strong>. Second-hand, inspected & cleaned.</p>',
                'canonical_url' => 'https://cleatsheat.pk/products/adidas-predator-football-shoes-used-1',
                'size_label' => 'UK 5',
                'uk_size' => '5',
                'price' => 7500,
                'stock_qty' => 1,
                'images' => [
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/6ba04997-3f62-4a71-b6cb-a67306f69ffc.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/35c5cc58-7bab-4cc1-aa0a-a06596430064.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/1a27263d-48b6-42af-9b0a-a4ef48088e11.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/28054056-5644-4a81-9d3f-696b53e5fdd6.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/6273ce51-caaa-493a-8194-a6096fc5e818.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/a8a579a1-3896-45c5-b0ab-897927240aec.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/7aeb032a-f3dd-4fd5-a1d2-9405adcd8c28.jpg',
                    'https://s3.us-east-005.backblazeb2.com/CleatSheatBucket/products/stock1/bf22e38a-abff-4ccd-b61b-604291212670.jpg',
                ],
            ],
        ];
    }
}
