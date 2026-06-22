<?php

namespace App\Http\Controllers\Web\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreCustomerReviewRequest;
use App\Models\CustomerReview;
use App\Models\MarketingSetting;
use App\Support\Seo\SeoPresenter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function create(SeoPresenter $seo): Response
    {
        $marketing = MarketingSetting::query()->first();
        $description = 'Tell us about your '.$seo->storeName().' order. Your feedback helps other players across Pakistan find the right boots.';

        return Inertia::render('Store/Review', [
            'seo' => $seo->mergeSocialTags(
                array_merge(
                    $seo->privatePageSeo('Share your experience', '/review', $description),
                    [
                        'og_title' => 'Share your experience — '.$seo->storeName(),
                        'og_description' => $description,
                        'og_type' => 'website',
                    ],
                ),
                $marketing?->default_og_image_url,
                $marketing?->twitter_site,
            ),
        ]);
    }

    public function store(StoreCustomerReviewRequest $request): RedirectResponse
    {
        CustomerReview::query()->create([
            ...$request->validated(),
            'is_published' => true,
        ]);

        return redirect()
            ->route('store.review')
            ->with('success', 'Thank you! Your review has been submitted and will appear on our homepage soon.');
    }
}
