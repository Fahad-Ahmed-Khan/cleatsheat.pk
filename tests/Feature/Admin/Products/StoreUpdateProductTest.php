<?php

namespace Tests\Feature\Admin\Products;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreUpdateProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_product_with_brand_id(): void
    {
        $this->seed(DemoCatalogSeeder::class);

        $admin = User::factory()->admin()->create();
        $brand = Brand::query()->first();
        $category = Category::query()->first();
        $color = Color::query()->first();

        $payload = [
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'size_chart_id' => '',
            'name' => 'Test Sneaker',
            'slug' => 'test-sneaker-x',
            'description' => '',
            'meta_title' => '',
            'meta_description' => '',
            'canonical_url' => '',
            'fit_guidance' => 'true_to_size',
            'gender' => 'men',
            'shoe_type' => 'sneaker',
            'fit_notes' => '',
            'size_info' => '',
            'features' => [''],
            'is_active' => true,
            'images' => [
                ['path' => '', 'file' => null, 'alt' => '', 'sort_order' => 0],
            ],
            'variants' => [
                [
                    'color_id' => $color->id,
                    'sku' => 'TEST-SKU-001',
                    'price' => 1000,
                    'compare_at_price' => '',
                    'is_active' => true,
                    'sizes' => [
                        [
                            'size_label' => 'UK 8',
                            'uk_size' => '8',
                            'eu_size' => '42',
                            'pk_size' => '8',
                            'stock_qty' => 5,
                            'low_stock_threshold' => 2,
                        ],
                    ],
                ],
            ],
        ];

        $resp = $this->actingAs($admin)
            ->from(route('admin.products.create'))
            ->post(route('admin.products.store'), $payload);

        $resp->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['slug' => 'test-sneaker-x', 'brand_id' => $brand->id]);
    }

    public function test_admin_can_create_product_with_file_upload_multipart(): void
    {
        $this->seed(DemoCatalogSeeder::class);
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $brand = Brand::query()->first();
        $category = Category::query()->first();
        $color = Color::query()->first();

        $payload = [
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'size_chart_id' => '',
            'name' => 'Multipart Sneaker',
            'slug' => 'multipart-sneaker',
            'description' => '',
            'meta_title' => '',
            'meta_description' => '',
            'canonical_url' => '',
            'fit_guidance' => 'true_to_size',
            'gender' => 'men',
            'shoe_type' => 'sneaker',
            'fit_notes' => '',
            'size_info' => '',
            'features' => [''],
            'is_active' => true,
            'images' => [
                [
                    'path' => '',
                    'file' => UploadedFile::fake()->image('shoe.jpg', 200, 200),
                    'alt' => '',
                    'sort_order' => 0,
                ],
            ],
            'variants' => [
                [
                    'color_id' => $color->id,
                    'sku' => 'MP-SKU-001',
                    'price' => 1000,
                    'compare_at_price' => '',
                    'is_active' => true,
                    'sizes' => [
                        [
                            'size_label' => 'UK 8',
                            'uk_size' => '8',
                            'eu_size' => '42',
                            'pk_size' => '8',
                            'stock_qty' => 5,
                            'low_stock_threshold' => 2,
                        ],
                    ],
                ],
            ],
        ];

        $resp = $this->actingAs($admin)
            ->from(route('admin.products.create'))
            ->post(route('admin.products.store'), $payload);

        $resp->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['slug' => 'multipart-sneaker', 'brand_id' => $brand->id]);
    }

    public function test_admin_can_update_product_with_file_upload_using_method_spoofing(): void
    {
        $this->seed(DemoCatalogSeeder::class);
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $product = Product::query()->with(['variants.sizes'])->firstOrFail();
        $newBrand = Brand::factory()->create();

        $variantsPayload = $product->variants->map(fn ($v) => [
            'color_id' => $v->color_id,
            'sku' => $v->sku,
            'price' => (float) $v->price,
            'compare_at_price' => '',
            'is_active' => $v->is_active,
            'sizes' => $v->sizes->map(fn ($s) => [
                'size_label' => $s->size_label,
                'uk_size' => $s->uk_size ?? '',
                'eu_size' => $s->eu_size ?? '',
                'pk_size' => $s->pk_size ?? '',
                'stock_qty' => $s->stock_qty,
                'low_stock_threshold' => $s->low_stock_threshold ?? 0,
            ])->all(),
        ])->all();

        $payload = [
            '_method' => 'put',
            'brand_id' => $newBrand->id,
            'category_id' => $product->category_id,
            'size_chart_id' => '',
            'name' => $product->name,
            'slug' => $product->slug,
            'fit_guidance' => $product->fit_guidance->value,
            'gender' => $product->gender->value,
            'shoe_type' => $product->shoe_type->value,
            'features' => [''],
            'is_active' => $product->is_active,
            'images' => [
                [
                    'path' => '',
                    'file' => UploadedFile::fake()->image('shoe.jpg', 200, 200),
                    'alt' => '',
                    'sort_order' => 0,
                ],
            ],
            'variants' => $variantsPayload,
        ];

        $resp = $this->actingAs($admin)
            ->from(route('admin.products.edit', $product))
            ->post(route('admin.products.update', $product), $payload);

        $resp->assertSessionHasNoErrors();
        $this->assertSame($newBrand->id, $product->fresh()->brand_id);
    }

    public function test_admin_can_save_product_video_url_and_poster(): void
    {
        $this->seed(DemoCatalogSeeder::class);

        $admin = User::factory()->admin()->create();
        $product = Product::query()->with(['variants.sizes'])->firstOrFail();

        $variantsPayload = $product->variants->map(fn ($v) => [
            'color_id' => $v->color_id,
            'sku' => $v->sku,
            'price' => (float) $v->price,
            'compare_at_price' => '',
            'is_active' => $v->is_active,
            'sizes' => $v->sizes->map(fn ($s) => [
                'size_label' => $s->size_label,
                'uk_size' => $s->uk_size ?? '',
                'eu_size' => $s->eu_size ?? '',
                'pk_size' => $s->pk_size ?? '',
                'stock_qty' => $s->stock_qty,
                'low_stock_threshold' => $s->low_stock_threshold ?? 0,
            ])->all(),
        ])->all();

        $payload = [
            'brand_id' => $product->brand_id,
            'category_id' => $product->category_id,
            'size_chart_id' => '',
            'name' => $product->name,
            'slug' => $product->slug,
            'fit_guidance' => $product->fit_guidance->value,
            'gender' => $product->gender->value,
            'shoe_type' => $product->shoe_type->value,
            'features' => [''],
            'is_active' => $product->is_active,
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'video_poster' => 'https://example.com/poster.jpg',
            'images' => [
                ['path' => '', 'file' => null, 'alt' => '', 'sort_order' => 0],
            ],
            'variants' => $variantsPayload,
        ];

        $resp = $this->actingAs($admin)
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), $payload);

        $resp->assertSessionHasNoErrors();
        $fresh = $product->fresh();
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $fresh->video_url);
        $this->assertSame('https://example.com/poster.jpg', $fresh->video_poster);
    }

    public function test_admin_can_upload_product_video_file_and_url_is_stored(): void
    {
        $this->seed(DemoCatalogSeeder::class);
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $product = Product::query()->with(['variants.sizes'])->firstOrFail();

        $variantsPayload = $product->variants->map(fn ($v) => [
            'color_id' => $v->color_id,
            'sku' => $v->sku,
            'price' => (float) $v->price,
            'compare_at_price' => '',
            'is_active' => $v->is_active,
            'sizes' => $v->sizes->map(fn ($s) => [
                'size_label' => $s->size_label,
                'uk_size' => $s->uk_size ?? '',
                'eu_size' => $s->eu_size ?? '',
                'pk_size' => $s->pk_size ?? '',
                'stock_qty' => $s->stock_qty,
                'low_stock_threshold' => $s->low_stock_threshold ?? 0,
            ])->all(),
        ])->all();

        $payload = [
            '_method' => 'put',
            'brand_id' => $product->brand_id,
            'category_id' => $product->category_id,
            'size_chart_id' => '',
            'name' => $product->name,
            'slug' => $product->slug,
            'fit_guidance' => $product->fit_guidance->value,
            'gender' => $product->gender->value,
            'shoe_type' => $product->shoe_type->value,
            'features' => [''],
            'is_active' => $product->is_active,
            'video_url' => '',
            'video_file' => UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4'),
            'images' => [
                ['path' => '', 'file' => null, 'alt' => '', 'sort_order' => 0],
            ],
            'variants' => $variantsPayload,
        ];

        $resp = $this->actingAs($admin)
            ->from(route('admin.products.edit', $product))
            ->post(route('admin.products.update', $product), $payload);

        $resp->assertSessionHasNoErrors();
        $fresh = $product->fresh();
        $this->assertNotNull($fresh->video_url);
        $this->assertStringContainsString('products/videos', $fresh->video_url);
    }

    public function test_admin_can_update_product_brand(): void
    {
        $this->seed(DemoCatalogSeeder::class);

        $admin = User::factory()->admin()->create();
        $product = Product::query()->with(['variants.sizes'])->firstOrFail();
        $newBrand = Brand::factory()->create();

        $variantsPayload = $product->variants->map(fn ($v) => [
            'color_id' => $v->color_id,
            'sku' => $v->sku,
            'price' => (float) $v->price,
            'compare_at_price' => '',
            'is_active' => $v->is_active,
            'sizes' => $v->sizes->map(fn ($s) => [
                'size_label' => $s->size_label,
                'uk_size' => $s->uk_size ?? '',
                'eu_size' => $s->eu_size ?? '',
                'pk_size' => $s->pk_size ?? '',
                'stock_qty' => $s->stock_qty,
                'low_stock_threshold' => $s->low_stock_threshold ?? 0,
            ])->all(),
        ])->all();

        $payload = [
            'brand_id' => $newBrand->id,
            'category_id' => $product->category_id,
            'size_chart_id' => '',
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description ?? '',
            'meta_title' => $product->meta_title ?? '',
            'meta_description' => $product->meta_description ?? '',
            'canonical_url' => $product->canonical_url ?? '',
            'fit_guidance' => $product->fit_guidance->value,
            'gender' => $product->gender->value,
            'shoe_type' => $product->shoe_type->value,
            'fit_notes' => $product->fit_notes ?? '',
            'size_info' => $product->size_info ?? '',
            'features' => [''],
            'is_active' => $product->is_active,
            'images' => [
                ['path' => '', 'file' => null, 'alt' => '', 'sort_order' => 0],
            ],
            'variants' => $variantsPayload,
        ];

        $resp = $this->actingAs($admin)
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), $payload);

        $resp->assertSessionHasNoErrors();
        $this->assertSame($newBrand->id, $product->fresh()->brand_id);
    }
}
