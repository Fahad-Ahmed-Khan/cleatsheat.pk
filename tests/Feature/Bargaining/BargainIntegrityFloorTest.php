<?php

namespace Tests\Feature\Bargaining;

use App\Models\BargainSession;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class BargainIntegrityFloorTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_range_nudge_respects_customer_integrity_floor_after_below_min_counter(): void
    {
        Config::set('bargain.ai.enabled', false);
        Config::set('bargain.in_range_nudge.enabled', true);
        Config::set('bargain.in_range_nudge.min_step_pkr', 25);
        Config::set('bargain.in_range_nudge.max_fraction_of_gap', 0.4);
        Config::set('bargain.counter.concession.enabled', false);
        Config::set('bargain.counter.min_step', 700);

        $this->seed(DemoCatalogSeeder::class);

        $variant = \App\Models\ProductVariant::query()->where('sku', 'URB-BLK-001')->firstOrFail();
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

        $below = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'PKR 12000 please',
        ]);
        $below->assertOk();
        $counter = (string) $below->json('data.assistant_message.meta.counter_offer');
        $this->assertTrue(bccomp($counter, '12000.00', 2) === 1, 'Counter should be above customer stated amount');

        $inRange = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/messages", [
            'customer_phone' => $phone,
            'message' => 'PKR 11500',
        ]);
        $inRange->assertOk();
        $nudged = (string) $inRange->json('data.assistant_message.meta.counter_offer');
        $this->assertTrue(bccomp($nudged, '12000.00', 2) >= 0, 'Shop line must not drop below integrity floor (12000)');
        $this->assertTrue(bccomp($nudged, $counter, 2) <= 0, 'Shop line must not increase vs prior counter');

        $session = BargainSession::query()->findOrFail($sessionId);
        $this->assertNotNull($session->customer_integrity_floor);
        $this->assertTrue(bccomp((string) $session->customer_integrity_floor, '12000.00', 2) >= 0);

        $accept = $this->postJson("/api/v1/bargain/sessions/{$sessionId}/accept", [
            'customer_phone' => $phone,
        ]);
        $accept->assertOk();
        $locked = (string) $accept->json('data.session.accepted_price');
        $this->assertTrue(bccomp($locked, '12000.00', 2) >= 0);
    }
}
