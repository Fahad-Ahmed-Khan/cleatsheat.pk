<?php

namespace Tests\Feature\Bargaining;

use App\Enums\BargainSessionState;
use App\Models\ProductVariant;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class BargainIntentTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_done_accepts_current_shop_line(): void
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
            'message' => 'theek hai, done',
        ]);
        $done->assertOk();
        $done->assertJsonPath('data.session.state', BargainSessionState::Accepted->value);
        $done->assertJsonPath('data.assistant_message.meta.kind', 'accepted');
        $this->assertNotEmpty($done->json('data.assistant_message.meta.checkout_token'));
    }

    public function test_ask_discount_does_not_change_current_offer(): void
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
        $sessionId = (int) $start->json('data.session.id');

        $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'PKR 920',
        ])->assertOk();

        $beforeOffer = (string) $this->getJson('/api/v1/bargain/sessions/'.$sessionId.'?customer_phone='.urlencode($phone))
            ->json('data.session.current_offer');

        $disc = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'kuch discount mil sakta hai?',
        ]);
        $disc->assertOk();
        $disc->assertJsonPath('data.assistant_message.meta.kind', 'intent_discount');
        $this->assertSame($beforeOffer, (string) $disc->json('data.session.current_offer'));
    }

    public function test_done_without_shop_line_clarifies_not_accepted(): void
    {
        Config::set('bargain.ai.enabled', false);

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
        $sessionId = (int) $start->json('data.session.id');

        $r = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'done',
        ]);
        $r->assertOk();
        $r->assertJsonPath('data.assistant_message.meta.kind', 'intent_clarify_accept');
        $r->assertJsonPath('data.session.state', BargainSessionState::Open->value);
    }
}
