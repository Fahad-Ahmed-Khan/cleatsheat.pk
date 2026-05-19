<?php

namespace Tests\Feature\Bargaining;

use App\Enums\BargainSessionState;
use App\Models\ProductVariant;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class BargainFinalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_after_acceptance_messages_are_rejected(): void
    {
        Config::set('bargain.ai.enabled', false);
        Config::set('bargain.counter.concession.enabled', false);
        Config::set('bargain.counter.min_step', 100);

        $this->seed(DemoCatalogSeeder::class);

        $variant = ProductVariant::query()->where('sku', 'URB-BLK-001')->firstOrFail();
        $variant->update([
            'bargain_enabled' => true,
            'bargain_min_price' => '900.00',
            'bargain_max_discount_percent' => '50.00',
            'price' => '1000.00',
        ]);

        $phone = '+923001234567';

        $start = $this->postJson('/api/v1/bargain/sessions', [
            'product_variant_id' => $variant->id,
            'customer_name' => 'Fahad',
            'customer_phone' => $phone,
        ]);
        $start->assertOk();
        $sessionId = (int) $start->json('data.session.id');

        $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'PKR 920 please',
        ])->assertOk();

        $done = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'done',
        ]);
        $done->assertOk();
        $done->assertJsonPath('data.session.state', BargainSessionState::Accepted->value);

        $after = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'acha 850 kar dein?',
        ]);

        $after->assertStatus(422);
    }
}
