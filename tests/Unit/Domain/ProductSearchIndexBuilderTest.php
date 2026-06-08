<?php

namespace Tests\Unit\Domain;

use App\Domain\Catalog\ProductSearchIndexBuilder;
use App\Models\Product;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchIndexBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_includes_brand_color_and_size_tokens(): void
    {
        $this->seed(DemoCatalogSeeder::class);

        $product = Product::query()->where('slug', 'urban-runner-pro')->firstOrFail();
        $builder = new ProductSearchIndexBuilder;
        $text = $builder->buildForProduct($product->load(['brand', 'category', 'variants.color', 'variants.sizes']));

        $this->assertStringContainsString('urban runner pro', $text);
        $this->assertStringContainsString('tryino originals', $text);
        $this->assertStringContainsString('sneakers', $text);
        $this->assertStringContainsString('black', $text);
        $this->assertStringContainsString('urb-blk-001', $text);
        $this->assertStringContainsString('uk 9', $text);
    }
}
