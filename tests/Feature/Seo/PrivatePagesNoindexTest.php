<?php

namespace Tests\Feature\Seo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivatePagesNoindexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_track_order_page_is_noindex_nofollow(): void
    {
        $this->get(route('store.order-tracking'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Store/OrderTracking')
                ->where('seo.robots', 'noindex, nofollow'));
    }

    public function test_review_page_is_noindex_nofollow(): void
    {
        $this->get(route('store.review'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Store/Review')
                ->where('seo.robots', 'noindex, nofollow'));
    }
}
