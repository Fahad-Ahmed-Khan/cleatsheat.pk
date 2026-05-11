<?php

namespace Tests\Feature\Bargaining;

use App\Domain\Checkout\CartService;
use App\Domain\Bargain\DeterministicShopkeeperReply;
use App\Models\Cart;
use App\Models\ProductVariant;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class BargainPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_templates_are_short_and_not_over_repetitive(): void
    {
        $ask = DeterministicShopkeeperReply::askForOfferWithAmount('12000.00');
        $this->assertStringNotContainsString('Listed price', $ask);
        $this->assertStringNotContainsString('Listed', $ask);

        $counter = DeterministicShopkeeperReply::counterTooLow('850.00', '900.00', '1000.00');
        $this->assertStringNotContainsString('Listed', $counter);

        $decline = DeterministicShopkeeperReply::decline();
        $this->assertStringNotContainsStringIgnoringCase('thanks', $decline);
        $this->assertStringNotContainsStringIgnoringCase('thank you', $decline);

        $nudge = DeterministicShopkeeperReply::nudgeIncreaseFromLastOffer('11000.00');
        $this->assertStringNotContainsStringIgnoringCase('budget', $nudge);
        $this->assertStringContainsString('PKR 11000.00', $nudge);
    }

    public function test_counter_offer_respects_minimum_allowed_price(): void
    {
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

        $start->assertOk()->assertJsonPath('success', true);
        $sessionId = (int) $start->json('data.session.id');

        $msg = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'Can you do PKR 850?',
        ]);

        $msg->assertOk()->assertJsonPath('success', true);

        $counter = (string) $msg->json('data.assistant_message.meta.counter_offer');
        $this->assertSame('900.00', $counter);
    }

    public function test_when_customer_sends_no_amount_after_a_previous_offer_shop_does_not_ask_budget_again(): void
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

        $start->assertOk()->assertJsonPath('success', true);
        $sessionId = (int) $start->json('data.session.id');

        $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'PKR 850',
        ])->assertOk();

        $followUp = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'please kar dein',
        ]);

        $followUp->assertOk();
        $body = (string) $followUp->json('data.assistant_message.body');
        $this->assertStringContainsString('PKR 850.00', $body);
        $this->assertStringNotContainsStringIgnoringCase('budget', $body);
        $this->assertStringNotContainsStringIgnoringCase('listed price', $body);
    }

    public function test_stepped_counter_moves_down_from_list_each_message(): void
    {
        Config::set('bargain.counter.concession.enabled', false);
        Config::set('bargain.counter.min_step', 100);

        $this->seed(DemoCatalogSeeder::class);

        $variant = ProductVariant::query()->where('sku', 'URB-BLK-001')->firstOrFail();
        $variant->update([
            'bargain_enabled' => true,
            'bargain_min_price' => '11300.00',
            'bargain_max_discount_percent' => '10.00',
            'price' => '12000.00',
        ]);

        $phone = '+923001234567';

        $start = $this->postJson('/api/v1/bargain/sessions', [
            'product_variant_id' => $variant->id,
            'customer_name' => 'Fahad',
            'customer_phone' => $phone,
        ]);
        $sessionId = (int) $start->json('data.session.id');

        $first = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'PKR 5000 please',
        ]);
        $first->assertOk();
        $this->assertSame('11900.00', (string) $first->json('data.assistant_message.meta.counter_offer'));

        $second = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'PKR 6000 still too much on my side',
        ]);
        $second->assertOk();
        $this->assertSame('11800.00', (string) $second->json('data.assistant_message.meta.counter_offer'));
    }

    public function test_customer_offer_at_or_above_previous_shop_offer_never_increases_price(): void
    {
        Config::set('bargain.in_range_nudge.enabled', true);
        Config::set('bargain.in_range_nudge.min_step_pkr', 25);
        Config::set('bargain.in_range_nudge.max_fraction_of_gap', 0.4);
        // Big enough step so we can get a prior shop offer below 11,600 quickly.
        Config::set('bargain.counter.concession.enabled', false);
        Config::set('bargain.counter.min_step', 700);

        $this->seed(DemoCatalogSeeder::class);

        $variant = ProductVariant::query()->where('sku', 'URB-BLK-001')->firstOrFail();
        $variant->update([
            'bargain_enabled' => true,
            'bargain_min_price' => '11300.00',
            'bargain_max_discount_percent' => '15.00',
            'price' => '12999.00',
        ]);

        $phone = '+923001234567';

        $start = $this->postJson('/api/v1/bargain/sessions', [
            'product_variant_id' => $variant->id,
            'customer_name' => 'Fahad',
            'customer_phone' => $phone,
        ]);
        $sessionId = (int) $start->json('data.session.id');

        // Force stepped counters (customer stays below floor) so we have a prior shop offer < 11,600.
        $firstLow = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'PKR 8000 please',
        ]);
        $firstLow->assertOk();

        $secondLow = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'PKR 8000 still',
        ]);
        $secondLow->assertOk();
        $prevShop = (string) $secondLow->json('data.assistant_message.meta.counter_offer');

        // Customer now offers at/above our own offer — engine must not walk price up (keep prev shop offer).
        $higher = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'PKR 11600',
        ]);
        $higher->assertOk();
        $this->assertSame($prevShop, (string) $higher->json('data.assistant_message.meta.counter_offer'));
        $this->assertSame($prevShop, (string) $higher->json('data.session.current_offer'));
    }

    public function test_shop_offer_never_increases_within_session_even_if_nudge_would_raise_it(): void
    {
        Config::set('bargain.in_range_nudge.enabled', true);
        Config::set('bargain.in_range_nudge.min_step_pkr', 25);
        Config::set('bargain.in_range_nudge.max_fraction_of_gap', 0.4);
        Config::set('bargain.counter.concession.enabled', false);
        Config::set('bargain.counter.min_step', 700);

        $this->seed(DemoCatalogSeeder::class);

        $variant = ProductVariant::query()->where('sku', 'URB-BLK-001')->firstOrFail();
        $variant->update([
            'bargain_enabled' => true,
            'bargain_min_price' => '11300.00',
            'bargain_max_discount_percent' => '15.00',
            'price' => '12999.00',
        ]);

        $phone = '+923001234567';

        $start = $this->postJson('/api/v1/bargain/sessions', [
            'product_variant_id' => $variant->id,
            'customer_name' => 'Fahad',
            'customer_phone' => $phone,
        ]);
        $sessionId = (int) $start->json('data.session.id');

        // Step down twice to get a prior shop line below list.
        $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'PKR 8000 please',
        ])->assertOk();

        $secondLow = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'PKR 8000 still',
        ]);
        $secondLow->assertOk();
        $prevShop = (string) $secondLow->json('data.assistant_message.meta.counter_offer');

        // Now send an in-range offer that would normally get nudged upward.
        $inRange = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'PKR 11600',
        ]);
        $inRange->assertOk();

        $newShop = (string) $inRange->json('data.assistant_message.meta.counter_offer');
        $this->assertSame($prevShop, $newShop, 'Shop offer should not increase above previous offer.');
    }

    public function test_accepted_price_applies_to_cart_line_for_matching_phone(): void
    {
        Config::set('bargain.in_range_nudge.min_step_pkr', 25);
        Config::set('bargain.in_range_nudge.max_fraction_of_gap', 0.4);
        Config::set('bargain.counter.concession.enabled', false);

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

        $msg = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'PKR 920 works for me.',
        ]);
        $msg->assertOk();
        $nudged = (string) $msg->json('data.session.current_offer');
        $this->assertSame('950.00', $nudged);
        $this->assertSame('acceptable_nudge', $msg->json('data.assistant_message.meta.kind'));

        $accept = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/accept", [
            'customer_phone' => $phone,
        ]);
        $accept->assertOk()->assertJsonPath('data.session.accepted_price', $nudged);

        $cart = Cart::query()->create([
            'user_id' => null,
            'guest_token' => '00000000-0000-0000-0000-000000000001',
            'currency' => 'PKR',
        ]);

        $cartService = app(CartService::class);
        $line = $cartService->addLine($cart, $variant->id, 'UK 8', 1, null, $phone);

        $this->assertSame($nudged, (string) $line->unit_price_snapshot);
    }

    public function test_accept_with_price_below_floor_is_rejected(): void
    {
        Config::set('bargain.counter.concession.enabled', false);
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

        $this->postJson("/api/v1/bargain/sessions/{$sessionId}/accept", [
            'customer_phone' => $phone,
            'price' => 850,
        ])->assertStatus(422);
    }

    public function test_seeded_concession_counters_are_monotonic_and_rounded(): void
    {
        Config::set('bargain.counter.concession.enabled', true);
        Config::set('bargain.counter.concession.randomness', 0.0);
        Config::set('bargain.counter.concession.round_to', 50);
        Config::set('bargain.counter.concession.min_step_pkr', 50);
        Config::set('bargain.counter.concession.near_floor_gap_threshold_pkr', 400);

        $this->seed(DemoCatalogSeeder::class);

        $variant = ProductVariant::query()->where('sku', 'URB-BLK-001')->firstOrFail();
        $variant->update([
            'bargain_enabled' => true,
            'bargain_min_price' => '11300.00',
            'bargain_max_discount_percent' => '10.00',
            'price' => '12000.00',
        ]);

        $phone = '+923001234567';

        $start = $this->postJson('/api/v1/bargain/sessions', [
            'product_variant_id' => $variant->id,
            'customer_name' => 'Fahad',
            'customer_phone' => $phone,
        ]);
        $sessionId = (int) $start->json('data.session.id');

        $first = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'PKR 5000 please',
        ]);
        $first->assertOk();
        $c1 = (string) $first->json('data.assistant_message.meta.counter_offer');

        $second = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'PKR 6000 still too much',
        ]);
        $second->assertOk();
        $c2 = (string) $second->json('data.assistant_message.meta.counter_offer');

        $this->assertMatchesRegularExpression('/^\d+\.00$/', $c1);
        $this->assertMatchesRegularExpression('/^\d+\.00$/', $c2);

        $this->assertTrue((float) $c1 < 12000.0);
        $this->assertTrue((float) $c1 >= 11300.0);
        $this->assertTrue((float) $c2 < (float) $c1);

        $step1 = (int) round(12000.0 - (float) $c1);
        $step2 = (int) round((float) $c1 - (float) $c2);

        $this->assertGreaterThanOrEqual(50, $step1);
        $this->assertGreaterThanOrEqual(50, $step2);
        $this->assertSame(0, $step1 % 50, 'First step should be rounded to 50 PKR.');
        $this->assertSame(0, $step2 % 50, 'Second step should be rounded to 50 PKR.');

        // Steps should generally get smaller near the floor, but rounding/seed can make a later step slightly larger.
        $this->assertTrue($step2 <= ($step1 + 50), 'Step size should not jump sharply as we approach the floor.');
    }
}
