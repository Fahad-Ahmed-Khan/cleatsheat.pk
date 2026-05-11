<?php

namespace Tests\Feature\Api\V1;

use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_login_and_auth_me(): void
    {
        $register = $this->postJson('/api/v1/auth/register', [
            'name' => 'API Customer',
            'email' => 'api.customer@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'device_name' => 'phpunit',
        ]);

        $register->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.api.path', 'api/v1')
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);

        $token = $register->json('data.token');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'api.customer@example.com',
            'password' => 'wrong',
        ])->assertUnauthorized()
            ->assertJsonPath('code', 'auth_invalid');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'api.customer@example.com',
            'password' => 'password',
        ]);

        $login->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'api.customer@example.com');

        $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('data.email', 'api.customer@example.com');
    }

    public function test_validation_errors_include_meta(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('meta.api.path', 'api/v1');
    }

    public function test_catalog_endpoints(): void
    {
        $this->seed(DemoCatalogSeeder::class);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/categories/sneakers/products')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/products/urban-runner-pro')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_orders_require_authentication(): void
    {
        $this->getJson('/api/v1/orders')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_guest_cart_header_checkout_cod(): void
    {
        $this->seed(DemoCatalogSeeder::class);

        $guest = (string) Str::uuid();
        $headers = ['X-Guest-Cart-Token' => $guest];

        $variant = ProductVariant::query()->where('sku', 'URB-BLK-001')->firstOrFail();

        $this->getJson('/api/v1/cart', $headers)->assertOk()->assertJsonPath('success', true);

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'size_label' => 'UK 8',
            'quantity' => 1,
        ], $headers)->assertOk();

        $this->postJson('/api/v1/checkout', [
            'full_name' => 'Guest Buyer',
            'phone' => '+923001234567',
            'line1' => 'Street 12',
            'city' => 'Karachi',
            'guest_email' => 'guest@example.com',
            'payment_gateway' => 'cod',
        ], $headers)->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_gateway', 'cod');

        $this->assertDatabaseHas('orders', [
            'payment_gateway' => 'cod',
        ]);
    }

    public function test_checkout_without_guest_email_when_bearer_user(): void
    {
        $this->seed(DemoCatalogSeeder::class);

        /** @var User $user */
        $user = User::factory()->create();
        $token = $user->createToken('phpunit')->plainTextToken;
        $auth = ['Authorization' => 'Bearer '.$token];

        $variant = ProductVariant::query()->where('sku', 'URB-BLK-001')->firstOrFail();

        $this->postJson('/api/v1/cart/items', [
            'product_variant_id' => $variant->id,
            'size_label' => 'UK 8',
            'quantity' => 1,
        ], $auth)->assertOk();

        $this->postJson('/api/v1/checkout', [
            'full_name' => 'Member Buyer',
            'phone' => '+923001234567',
            'line1' => 'Street 12',
            'city' => 'Karachi',
            'payment_gateway' => 'cod',
        ], $auth)->assertCreated()
            ->assertJsonPath('data.payment_gateway', 'cod');
    }
}
