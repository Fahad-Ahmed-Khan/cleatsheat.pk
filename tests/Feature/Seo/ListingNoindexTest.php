<?php

namespace Tests\Feature\Seo;

use App\Models\Product;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingNoindexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoCatalogSeeder::class);
    }

    public function test_shop_without_filters_has_no_robots_meta(): void
    {
        $base = rtrim((string) config('app.url'), '/');

        $this->get(route('store.shop'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Store/Shop')
                ->where('seo.canonical', $base.'/shop')
                ->where('seo.robots', null));
    }

    public function test_shop_with_brand_filter_sets_noindex_follow(): void
    {
        $brandId = Product::query()->where('slug', 'urban-runner-pro')->value('brand_id');
        $base = rtrim((string) config('app.url'), '/');

        $this->get(route('store.shop', ['brand_ids' => [$brandId]]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Store/Shop')
                ->where('seo.robots', 'noindex, follow')
                ->where('seo.canonical', $base.'/shop'));
    }

    public function test_category_with_sort_sets_noindex_follow(): void
    {
        $this->get(route('store.category', 'sneakers').'?sort=price_asc')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Store/Category')
                ->where('seo.robots', 'noindex, follow'));
    }

    public function test_shop_page_two_sets_noindex_follow(): void
    {
        $this->get(route('store.shop', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Store/Shop')
                ->where('seo.robots', 'noindex, follow'));
    }

    public function test_category_without_filters_has_no_robots_meta(): void
    {
        $base = rtrim((string) config('app.url'), '/');

        $this->get(route('store.category', 'sneakers'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Store/Category')
                ->where('seo.canonical', $base.'/c/sneakers')
                ->where('seo.robots', null));
    }
}
