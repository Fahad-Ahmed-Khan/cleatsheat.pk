<?php

namespace Tests\Feature\Store;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_routes_require_authentication(): void
    {
        $this->get(route('store.account.dashboard'))->assertRedirect(route('login'));
        $this->get(route('store.account.orders.index'))->assertRedirect(route('login'));
        $this->get(route('store.account.wishlist'))->assertRedirect(route('login'));
        $this->get(route('store.account.addresses'))->assertRedirect(route('login'));
    }

    public function test_customer_can_view_own_order_but_not_another_users_order(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $owned = Order::query()->create([
            'order_number' => 'ORD-OWN-001',
            'user_id' => $owner->id,
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
            'payment_gateway' => 'cod',
            'subtotal' => 1000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'cod_fee' => 0,
            'grand_total' => 1000,
            'shipping_address_snapshot' => [
                'full_name' => 'Owner',
                'phone' => '+923001234567',
                'line1' => 'Line 1',
                'city' => 'Lahore',
            ],
        ]);

        Order::query()->create([
            'order_number' => 'ORD-OTHER-001',
            'user_id' => $other->id,
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
            'payment_gateway' => 'cod',
            'subtotal' => 500,
            'discount_total' => 0,
            'shipping_total' => 0,
            'cod_fee' => 0,
            'grand_total' => 500,
            'shipping_address_snapshot' => [
                'full_name' => 'Other',
                'phone' => '+923009999999',
                'line1' => 'Line 2',
                'city' => 'Karachi',
            ],
        ]);

        $this->actingAs($owner)
            ->get(route('store.account.orders.show', $owned->order_number))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('store.account.orders.show', 'ORD-OTHER-001'))
            ->assertNotFound();
    }

    public function test_legacy_order_url_redirects_to_account_order_show(): void
    {
        $user = User::factory()->create();
        $order = Order::query()->create([
            'order_number' => 'ORD-LEG-001',
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
            'payment_gateway' => 'cod',
            'subtotal' => 100,
            'discount_total' => 0,
            'shipping_total' => 0,
            'cod_fee' => 0,
            'grand_total' => 100,
            'shipping_address_snapshot' => [
                'full_name' => 'Test',
                'phone' => '+923001234567',
                'line1' => 'Line',
                'city' => 'Lahore',
            ],
        ]);

        $this->actingAs($user)
            ->get('/orders/'.$order->order_number)
            ->assertRedirect(route('store.account.orders.show', $order->order_number));
    }

    public function test_wishlist_toggle_is_idempotent_for_authenticated_user(): void
    {
        $this->seed(DemoCatalogSeeder::class);
        $user = User::factory()->create();
        $product = Product::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('store.account.wishlist.store', $product))
            ->assertRedirect();

        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $this->actingAs($user)
            ->post(route('store.account.wishlist.store', $product))
            ->assertRedirect();

        $this->assertSame(1, WishlistItem::query()->where('user_id', $user->id)->where('product_id', $product->id)->count());
    }

    public function test_setting_default_address_clears_other_defaults(): void
    {
        $user = User::factory()->create();

        $first = Address::query()->create([
            'user_id' => $user->id,
            'full_name' => 'A',
            'phone' => '+923001234567',
            'line1' => 'Street 1',
            'city' => 'Lahore',
            'is_default' => true,
        ]);

        $second = Address::query()->create([
            'user_id' => $user->id,
            'full_name' => 'B',
            'phone' => '+923001234568',
            'line1' => 'Street 2',
            'city' => 'Karachi',
            'is_default' => false,
        ]);

        $this->actingAs($user)
            ->patch(route('store.account.addresses.update', $second), [
                'full_name' => 'B',
                'phone' => '+923001234568',
                'line1' => 'Street 2',
                'city' => 'Karachi',
                'area' => null,
                'postal_code' => null,
                'is_default' => true,
            ])
            ->assertRedirect(route('store.account.addresses'));

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_dashboard_redirects_from_legacy_dashboard_route(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/account');
    }
}
