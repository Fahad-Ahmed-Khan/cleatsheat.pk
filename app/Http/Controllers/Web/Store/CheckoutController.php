<?php

namespace App\Http\Controllers\Web\Store;

use App\Domain\Checkout\CartService;
use App\Domain\Checkout\CheckoutService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\CheckoutStoreRequest;
use App\Models\MarketingSetting;
use App\Models\Order;
use App\Models\PaymentMethodConfig;
use App\Support\Seo\SeoPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CheckoutService $checkoutService,
    ) {}

    public function create(Request $request, SeoPresenter $seo): Response|RedirectResponse
    {
        $cart = $this->cartService->getOrCreateCart($request->user(), $request);
        if ($cart->items()->doesntExist()) {
            return redirect()->route('store.cart');
        }

        $cart->load(['items.variant.product']);
        $value = (float) $cart->items->sum(fn ($i) => (float) $i->unit_price_snapshot * (int) $i->quantity);
        $analyticsItems = $cart->items->map(fn ($it) => [
            'item_id' => (string) $it->variant->product_id,
            'item_name' => $it->variant->product->name,
            'quantity' => (int) $it->quantity,
            'price' => (float) $it->unit_price_snapshot,
        ])->values()->all();

        $m = MarketingSetting::query()->first();
        $canonical = rtrim(config('app.url'), '/').'/checkout';
        $description = 'Secure checkout for shoe orders — name, phone, delivery city, and payment method.';
        $seoPayload = $seo->mergeSocialTags([
            'title' => 'Checkout — '.config('app.name'),
            'description' => $description,
            'canonical' => $canonical,
            'og_title' => 'Checkout',
            'og_description' => $description,
            'og_type' => 'website',
            'robots' => 'noindex, nofollow',
        ], $m?->default_og_image_url, $m?->twitter_site);

        $savedAddresses = [];
        if ($request->user()) {
            $savedAddresses = $request->user()
                ->addresses()
                ->orderByDesc('is_default')
                ->orderByDesc('id')
                ->get(['id', 'full_name', 'phone', 'line1', 'city', 'area', 'postal_code', 'is_default'])
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'full_name' => $a->full_name,
                    'phone' => $a->phone,
                    'line1' => $a->line1,
                    'city' => $a->city,
                    'area' => $a->area,
                    'postal_code' => $a->postal_code,
                    'is_default' => (bool) $a->is_default,
                ])
                ->values()
                ->all();
        }

        return Inertia::render('Store/Checkout', [
            'seo' => $seoPayload,
            'savedAddresses' => $savedAddresses,
            'analytics_checkout' => [
                'value' => $value,
                'currency' => 'PKR',
                'items' => $analyticsItems,
            ],
            'gateways' => PaymentMethodConfig::enabledOrdered()->map(fn ($c) => [
                'code' => $c->gateway_code,
                'label' => $c->customer_label,
                'fee_fixed' => (float) $c->fee_fixed,
                'fee_percent' => (float) $c->fee_percent,
            ])->values()->all(),
        ]);
    }

    public function store(CheckoutStoreRequest $request): RedirectResponse|HttpResponse
    {
        $cart = $this->cartService->getOrCreateCart($request->user(), $request);
        if ($cart->items()->doesntExist()) {
            return redirect()->route('store.cart')->withErrors(['checkout' => 'Your bag is empty']);
        }

        $data = $request->validated();

        try {
            $placement = $this->checkoutService->placeOrder(
                $cart,
                $data,
                $data['payment_gateway'],
                $request->user(),
                $data['guest_email'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()])->withInput();
        }

        $init = $placement->paymentInit;
        if ($init !== null && $init->redirectUrl !== null && $init->redirectUrl !== '') {
            // Inertia intercepts plain redirect()->away() as an XHR follow, which fails
            // for cross-origin gateway URLs (Safepay) because of CORS. Inertia::location
            // returns the 409 + X-Inertia-Location header that triggers a real browser
            // navigation, and falls back gracefully for non-Inertia callers.
            if ($request->header('X-Inertia')) {
                return Inertia::location($init->redirectUrl);
            }

            return redirect()->away($init->redirectUrl);
        }

        $request->session()->flash('thank_you_order_id', $placement->order->id);

        return redirect()->route('store.checkout.thankyou');
    }

    public function thankYou(Request $request, SeoPresenter $seo): Response|RedirectResponse
    {
        $orderId = $request->session()->pull('thank_you_order_id');
        if (! $orderId) {
            return redirect()->route('store.home');
        }

        $order = Order::query()->with(['items.variant'])->findOrFail($orderId);

        $m = MarketingSetting::query()->first();
        $canonical = rtrim(config('app.url'), '/').'/checkout/thank-you';
        $description = 'Your Tryino order confirmation.';
        $seoPayload = $seo->mergeSocialTags([
            'title' => 'Thank you — '.config('app.name'),
            'description' => $description,
            'canonical' => $canonical,
            'og_title' => 'Order placed',
            'og_description' => $description,
            'og_type' => 'website',
            'robots' => 'noindex, nofollow',
        ], $m?->default_og_image_url, $m?->twitter_site);

        return Inertia::render('Store/ThankYou', [
            'notice' => $request->session()->pull('payment_notice'),
            'order' => [
                'order_number' => $order->order_number,
                'grand_total' => (float) $order->grand_total,
                'payment_gateway' => $order->payment_gateway,
                'payment_status' => $order->payment_status->value,
                'items' => $order->items->map(fn ($i) => [
                    'product_id' => $i->variant?->product_id,
                    'product_variant_id' => $i->product_variant_id,
                    'product_name' => $i->product_name,
                    'variant_label' => $i->variant_label,
                    'size_label' => $i->size_label,
                    'quantity' => $i->quantity,
                    'line_total' => (float) $i->line_total,
                ]),
            ],
            'seo' => $seoPayload,
        ]);
    }
}
