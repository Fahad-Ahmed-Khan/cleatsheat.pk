<?php

namespace App\Http\Controllers\Web\Store;

use App\Domain\Checkout\CartService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\AddToCartRequest;
use App\Models\CartItem;
use App\Models\MarketingSetting;
use App\Support\Seo\SeoPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
    ) {}

    public function index(Request $request, SeoPresenter $seo): Response
    {
        $cartModel = $this->cart->getOrCreateCart($request->user(), $request);
        $this->cart->revertStaleBargainLines($cartModel);
        $cartModel->load(['items.variant.product.images', 'items.variant.color']);

        $lines = $cartModel->items->map(function (CartItem $item) {
            $v = $item->variant;
            $img = $v->product->images->first();

            return [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'size_label' => $item->size_label,
                'unit_price' => (float) $item->unit_price_snapshot,
                'line_total' => round((float) $item->unit_price_snapshot * $item->quantity, 2),
                'variant' => [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'color' => $v->color->name,
                ],
                'product' => [
                    'id' => $v->product->id,
                    'name' => $v->product->name,
                    'slug' => $v->product->slug,
                    'image' => $img?->path,
                ],
            ];
        });

        $subtotal = $lines->sum('line_total');

        $m = MarketingSetting::query()->first();
        $canonical = rtrim(config('app.url'), '/').'/cart';
        $description = 'Review your shoe bag — sizes, colours, and PKR totals before checkout.';
        $seoPayload = $seo->mergeSocialTags([
            'title' => 'Shopping bag — '.config('app.name'),
            'description' => $description,
            'canonical' => $canonical,
            'og_title' => 'Shopping bag',
            'og_description' => $description,
            'og_type' => 'website',
            'robots' => 'noindex, nofollow',
        ], $m?->default_og_image_url, $m?->twitter_site);

        return Inertia::render('Store/Cart', [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'seo' => $seoPayload,
        ]);
    }

    public function store(AddToCartRequest $request): RedirectResponse
    {
        $cart = $this->cart->getOrCreateCart($request->user(), $request);

        try {
            $this->cart->addLine(
                $cart,
                (int) $request->validated('product_variant_id'),
                $request->validated('size_label'),
                (int) $request->validated('quantity'),
                $request->user(),
                $request->input('bargain_phone'),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        return redirect()->route('store.cart')->with('status', 'Added to bag');
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $this->authorizeItem($request, $item);

        $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:20']]);

        try {
            $this->cart->updateQuantity($item, (int) $request->input('quantity'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        return back();
    }

    public function destroy(Request $request, CartItem $item): RedirectResponse
    {
        $this->authorizeItem($request, $item);
        $item->delete();

        return back();
    }

    private function authorizeItem(Request $request, CartItem $item): void
    {
        $cart = $this->cart->getOrCreateCart($request->user(), $request);
        abort_unless($item->cart_id === $cart->id, 403);
    }
}
