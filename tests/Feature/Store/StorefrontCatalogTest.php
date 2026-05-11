<?php

namespace Tests\Feature\Store;

use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads(): void
    {
        $this->seed(DemoCatalogSeeder::class);

        $response = $this->get(route('store.home'));

        $response->assertStatus(200);
    }

    public function test_product_detail_loads_for_seeded_slug(): void
    {
        $this->seed(DemoCatalogSeeder::class);

        $response = $this->get(route('store.product', ['slug' => 'urban-runner-pro']));

        $response->assertStatus(200);
    }
}
