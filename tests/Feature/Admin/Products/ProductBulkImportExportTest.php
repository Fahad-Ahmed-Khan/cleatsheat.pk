<?php

namespace Tests\Feature\Admin\Products;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductBulkImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_csv_with_seeded_product_slug(): void
    {
        $this->seed(DemoCatalogSeeder::class);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.products.export', ['format' => 'csv']));

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertStringContainsString('urban-runner-pro', $body);
        $this->assertStringContainsString('URB-BLK-001', $body);
    }

    public function test_admin_can_import_csv_to_create_product(): void
    {
        $this->seed(DemoCatalogSeeder::class);
        $admin = User::factory()->admin()->create();

        $csv = <<<'CSV'
product_id,brand_slug,brand_id,category_slug,category_id,size_chart_id,name,slug,description,meta_title,meta_description,canonical_url,video_url,video_poster,fit_guidance,gender,shoe_type,fit_notes,size_info,features,product_is_active,image_paths,color_slug,color_id,sku,price,compare_at_price,variant_is_active,bargain_enabled,bargain_min_price,bargain_max_discount_percent,size_label,uk_size,eu_size,pk_size,stock_qty,low_stock_threshold
,tryino-originals,,sneakers,,,Bulk CSV Shoe,bulk-csv-shoe,,,,,,,true_to_size,men,sneaker,,,,1,,black,,BULK-SKU-1,2500,,1,0,,,UK 8,8,42,8,4,1
,tryino-originals,,sneakers,,,Bulk CSV Shoe,bulk-csv-shoe,,,,,,,true_to_size,men,sneaker,,,,1,,black,,BULK-SKU-1,2500,,1,0,,,UK 9,9,43,9,2,1
CSV;

        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs($admin)->post(route('admin.products.import'), [
            'file' => $file,
        ]);

        $this->assertNull(session('error'));
        $this->assertNotNull(session('status'));
        $this->assertDatabaseHas('products', ['slug' => 'bulk-csv-shoe', 'name' => 'Bulk CSV Shoe']);
        $this->assertDatabaseHas('product_variants', ['sku' => 'BULK-SKU-1']);
        $product = Product::query()->where('slug', 'bulk-csv-shoe')->first();
        $this->assertNotNull($product);
        $this->assertSame(2, (int) $product->variants()->first()?->sizes()->count());
    }
}
