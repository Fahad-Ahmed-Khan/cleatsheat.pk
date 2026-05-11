<?php

namespace Tests\Feature\Store;

use App\Models\NotificationLog;
use App\Models\ProductVariant;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_place_cod_order_from_demo_catalog(): void
    {
        $this->seed(DemoCatalogSeeder::class);

        $variant = ProductVariant::query()->where('sku', 'URB-BLK-001')->firstOrFail();
        $sizeLabel = 'UK 8';

        $this->post(route('store.cart.add'), [
            'product_variant_id' => $variant->id,
            'size_label' => $sizeLabel,
            'quantity' => 1,
        ])->assertRedirect(route('store.cart'));

        $this->post(route('store.checkout.store'), [
            'full_name' => 'Test Customer',
            'phone' => '+923001234567',
            'line1' => 'Street 12',
            'city' => 'Karachi',
            'guest_email' => 'buyer@example.com',
            'payment_gateway' => 'cod',
        ])->assertRedirect(route('store.checkout.thankyou'));

        $this->assertDatabaseHas('orders', [
            'payment_gateway' => 'cod',
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'channel' => 'whatsapp',
            'template_key' => 'order_placed',
            'status' => 'sent',
        ]);
        $this->assertSame(1, NotificationLog::query()->where('template_key', 'order_placed')->count());
    }
}
