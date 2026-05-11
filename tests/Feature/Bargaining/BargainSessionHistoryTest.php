<?php

namespace Tests\Feature\Bargaining;

use App\Models\ProductVariant;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BargainSessionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_returns_persisted_messages_after_refresh(): void
    {
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
            'message' => 'Can you do PKR 850?',
        ])->assertOk();

        // Simulate refresh: call status and ensure history includes welcome + customer + assistant.
        $status = $this->getJson("/api/v1/bargain/sessions/{$sessionId}?customer_phone=".urlencode($phone));
        $status->assertOk();

        $messages = $status->json('data.session.messages');
        $this->assertIsArray($messages);
        $this->assertGreaterThanOrEqual(3, count($messages));

        $this->assertSame('assistant', $messages[0]['role']);
        $this->assertSame('customer', $messages[1]['role']);
        $this->assertSame('assistant', $messages[2]['role']);
    }
}

