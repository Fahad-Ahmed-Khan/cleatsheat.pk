<?php

namespace Tests\Feature\Store;

use App\Models\CustomerReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_can_view_review_form(): void
    {
        $this->get(route('store.review'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Store/Review'));
    }

    public function test_guest_can_submit_review(): void
    {
        $this->post(route('store.review.store'), [
            'author_name' => 'Hassan R.',
            'city' => 'Lahore',
            'quote' => 'Boots arrived exactly as listed. COD was smooth and sizing was spot on.',
            'rating' => 5,
            'email' => 'hassan@example.com',
        ])
            ->assertRedirect(route('store.review'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('customer_reviews', [
            'author_name' => 'Hassan R.',
            'city' => 'Lahore',
            'rating' => 5,
            'is_published' => true,
        ]);
    }

    public function test_submitted_reviews_appear_on_homepage_testimonials(): void
    {
        CustomerReview::query()->create([
            'author_name' => 'Ali K.',
            'city' => 'Karachi',
            'quote' => 'WhatsApp sizing help nailed it.',
            'rating' => 5,
            'is_published' => true,
        ]);

        $this->get(route('store.home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('homeContent.testimonials', 1)
                ->where('homeContent.testimonials.0.name', 'Ali K.')
                ->where('homeContent.testimonials.0.city', 'Karachi')
                ->where('homeContent.testimonials.0.quote', 'WhatsApp sizing help nailed it.')
                ->where('homeContent.testimonials.0.rating', 5));
    }

    public function test_admin_can_view_customer_reviews_with_qr(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.customer-reviews.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/CustomerReviews/Index')
                ->has('reviewFormUrl')
                ->has('qrSvg'));
    }

    public function test_admin_can_remove_customer_review(): void
    {
        $admin = User::factory()->admin()->create();
        $review = CustomerReview::query()->create([
            'author_name' => 'Usman T.',
            'city' => 'Islamabad',
            'quote' => 'Authentic used boots at fair PKR.',
            'rating' => 5,
            'is_published' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.customer-reviews.destroy', $review))
            ->assertRedirect(route('admin.customer-reviews.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('customer_reviews', ['id' => $review->id]);
    }
}
