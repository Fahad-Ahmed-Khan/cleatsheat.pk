<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerReview;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CustomerReviewAdminController extends Controller
{
    public function index(): Response
    {
        $reviews = CustomerReview::query()
            ->latest()
            ->paginate(25);

        $reviewFormUrl = route('store.review');
        $qrSvg = QrCode::size(220)->margin(1)->generate($reviewFormUrl);

        return Inertia::render('Admin/CustomerReviews/Index', [
            'reviews' => $reviews,
            'reviewFormUrl' => $reviewFormUrl,
            'qrSvg' => (string) $qrSvg,
        ]);
    }

    public function destroy(CustomerReview $customer_review): RedirectResponse
    {
        $customer_review->delete();

        return redirect()
            ->route('admin.customer-reviews.index')
            ->with('status', 'Review removed.');
    }
}
