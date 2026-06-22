<?php

namespace App\Http\Controllers\Web\Store;

use App\Domain\Orders\CustomerOrderQueryService;
use App\Http\Controllers\Controller;
use App\Models\MarketingSetting;
use App\Support\Seo\SeoPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderTrackingController extends Controller
{
    public function __construct(
        private readonly CustomerOrderQueryService $orders,
    ) {}

    public function show(Request $request, SeoPresenter $seo): Response
    {
        $seoPayload = $this->trackingSeo($seo);

        // Deep link support (e.g. WhatsApp "Track Order" buttons): /track-order?order=TR-XXXX
        $orderNumber = trim((string) $request->query('order', ''));

        if ($orderNumber !== '') {
            $resolved = $this->orders->lookupForPublicTracking($orderNumber, null, null);

            if ($resolved['error'] === null) {
                return Inertia::render('Store/OrderTracking', [
                    'seo' => $seoPayload,
                    'result' => $resolved['result'],
                    'choices' => $resolved['choices'],
                    'lookup' => ['mode' => 'order_number', 'email' => '', 'phone' => ''],
                ]);
            }
        }

        return Inertia::render('Store/OrderTracking', [
            'seo' => $seoPayload,
            'result' => null,
            'choices' => [],
            'lookup' => null,
            'prefill_order_number' => $orderNumber !== '' ? $orderNumber : null,
        ]);
    }

    public function lookup(Request $request, SeoPresenter $seo): Response|RedirectResponse
    {
        $mode = $request->input('lookup_mode', 'order_number');
        if (! in_array($mode, ['order_number', 'email', 'phone'], true)) {
            $mode = 'order_number';
        }

        $data = $request->validate(match ($mode) {
            'email' => [
                'lookup_mode' => ['required', 'in:email'],
                'email' => ['required', 'email', 'max:255'],
            ],
            'phone' => [
                'lookup_mode' => ['required', 'in:phone'],
                'phone' => ['required', 'string', 'max:32'],
            ],
            default => [
                'lookup_mode' => ['required', 'in:order_number'],
                'order_number' => ['required', 'string', 'max:64'],
            ],
        });

        $resolved = $this->orders->lookupForPublicTracking(
            $mode === 'order_number' ? ($data['order_number'] ?? null) : null,
            $mode === 'email' ? ($data['email'] ?? null) : null,
            $mode === 'phone' ? ($data['phone'] ?? null) : null,
        );

        if ($resolved['error'] !== null) {
            return back()->withErrors([
                'lookup' => $resolved['error'],
            ])->withInput();
        }

        return Inertia::render('Store/OrderTracking', [
            'seo' => $this->trackingSeo($seo),
            'result' => $resolved['result'],
            'choices' => $resolved['choices'],
            'lookup' => [
                'mode' => $mode,
                'email' => $data['email'] ?? '',
                'phone' => $data['phone'] ?? '',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function trackingSeo(SeoPresenter $seo): array
    {
        $m = MarketingSetting::query()->first();

        return $seo->mergeSocialTags(
            $seo->privatePageSeo('Track order', '/track-order', 'Track your order delivery status.'),
            $m?->default_og_image_url,
            $m?->twitter_site,
        );
    }
}
