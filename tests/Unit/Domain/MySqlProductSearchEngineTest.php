<?php

namespace Tests\Unit\Domain;

use App\Domain\Catalog\MySqlProductSearchEngine;
use App\Domain\Catalog\ProductListFilterApplicator;
use App\Domain\Catalog\ProductSearchIndexBuilder;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MySqlProductSearchEngineTest extends TestCase
{
    use RefreshDatabase;

    private MySqlProductSearchEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new MySqlProductSearchEngine(new ProductListFilterApplicator);
    }

    public function test_exact_slug_match_ranks_above_prefix_match(): void
    {
        $this->seed(DemoCatalogSeeder::class);

        $brand = Brand::query()->firstOrFail();
        $category = Category::query()->firstOrFail();

        $prefixOnly = Product::factory()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => 'Urban Lifestyle Sneaker',
            'slug' => 'urban-lifestyle-sneaker',
            'is_active' => true,
        ]);

        $exact = Product::query()->where('slug', 'urban-runner-pro')->firstOrFail();

        $builder = new ProductSearchIndexBuilder;
        $builder->rebuildProduct((int) $prefixOnly->id);
        $builder->rebuildProduct((int) $exact->id);

        $result = $this->engine->search('urban-runner-pro', [], 12);
        $items = $result['paginator']->items();

        $this->assertNotEmpty($items);
        $this->assertSame('urban-runner-pro', $items[0]->slug);
    }

    public function test_prefix_name_match_finds_products(): void
    {
        $this->seed(DemoCatalogSeeder::class);

        $product = Product::query()->where('slug', 'urban-runner-pro')->firstOrFail();
        (new ProductSearchIndexBuilder)->rebuildProduct((int) $product->id);

        $result = $this->engine->search('urban run', [], 12);

        $this->assertGreaterThan(0, $result['paginator']->total());
        $this->assertTrue(
            collect($result['paginator']->items())->contains(fn (Product $p) => $p->slug === 'urban-runner-pro')
        );
    }

    public function test_no_match_returns_zero_results_without_fallback_flag(): void
    {
        $this->seed(DemoCatalogSeeder::class);

        $result = $this->engine->search('zzzznotfound999', [], 12);

        $this->assertSame(0, $result['paginator']->total());
        $this->assertNull($result['meta']['fallback']);
    }
}
