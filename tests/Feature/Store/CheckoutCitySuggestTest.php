<?php

namespace Tests\Feature\Store;

use App\Models\Courier;
use Database\Seeders\ShippingCourierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutCitySuggestTest extends TestCase
{
    use RefreshDatabase;

    public function test_city_suggest_returns_json_shape(): void
    {
        $this->seed(ShippingCourierSeeder::class);
        $this->assertNotNull(Courier::query()->where('code', 'trax')->first());

        $this->getJson(route('store.checkout.cities', ['q' => 'kar']))
            ->assertOk()
            ->assertJsonStructure(['cities']);
    }
}

