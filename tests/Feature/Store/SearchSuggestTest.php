<?php

namespace Tests\Feature\Store;

use App\Domain\Catalog\ProductSearchIndexBuilder;
use App\Models\Product;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SearchSuggestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoCatalogSeeder::class);

        $product = Product::query()->where('slug', 'urban-runner-pro')->firstOrFail();
        (new ProductSearchIndexBuilder)->rebuildProduct((int) $product->id);
    }

    public function test_suggest_returns_product_json(): void
    {
        $this->getJson(route('store.search.suggest', ['q' => 'urb']))
            ->assertOk()
            ->assertJsonStructure([
                'products',
                'brands',
                'categories',
                'terms',
            ])
            ->assertJson(fn ($json) => $json->has('products', 1)->etc());
    }

    public function test_suggest_short_query_returns_empty(): void
    {
        $this->getJson(route('store.search.suggest', ['q' => 'u']))
            ->assertOk()
            ->assertJson([
                'products' => [],
                'brands' => [],
                'categories' => [],
                'terms' => [],
            ]);
    }

    public function test_suggest_route_is_throttled(): void
    {
        $route = collect(Route::getRoutes())->first(
            fn ($r) => $r->getName() === 'store.search.suggest'
        );

        $this->assertNotNull($route);
        $this->assertContains('throttle:search-suggest', $route->gatherMiddleware());
    }
}
