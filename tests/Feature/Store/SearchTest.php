<?php

namespace Tests\Feature\Store;

use App\Domain\Catalog\ProductSearchIndexBuilder;
use App\Models\Product;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoCatalogSeeder::class);

        $product = Product::query()->where('slug', 'urban-runner-pro')->firstOrFail();
        (new ProductSearchIndexBuilder)->rebuildProduct((int) $product->id);
    }

    public function test_empty_search_redirects_to_shop(): void
    {
        $this->get(route('store.search'))
            ->assertRedirect(route('store.shop'));
    }

    public function test_search_returns_matching_product(): void
    {
        $this->get(route('store.search', ['q' => 'urban runner']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Store/Search')
                ->has('products', 1)
                ->where('searchMeta.query', 'urban runner')
            );
    }

    public function test_search_with_query_sets_noindex_robots_meta(): void
    {
        $response = $this->get(route('store.search', ['q' => 'urban']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('seo.robots', 'noindex, follow')
        );
    }

    public function test_search_composes_with_brand_filter(): void
    {
        $brandId = Product::query()->where('slug', 'urban-runner-pro')->value('brand_id');

        $this->get(route('store.search', [
            'q' => 'urban',
            'brand_ids' => [$brandId],
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('products', 1));
    }

    public function test_invalid_query_characters_are_rejected(): void
    {
        $this->get(route('store.search', ['q' => 'nike+mercurial']))
            ->assertSessionHasErrors('q');
    }
}
