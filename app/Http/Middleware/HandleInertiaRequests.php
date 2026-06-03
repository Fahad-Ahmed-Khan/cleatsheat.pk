<?php

namespace App\Http\Middleware;

use App\Domain\Wishlist\WishlistService;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\MarketingSetting;
use App\Models\StorefrontSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Lightweight cart-item count for the current user/session.
     * Read-only — does NOT create a cart, so anonymous browsers stay clean.
     */
    private function resolveCartCount(Request $request): int
    {
        if (! Schema::hasTable('cart_items') || ! Schema::hasTable('carts')) {
            return 0;
        }

        $userId = $request->user()?->id;
        $token = $request->hasSession() ? $request->session()->get('guest_cart_token') : null;

        if ($userId === null && $token === null) {
            return 0;
        }

        return (int) CartItem::query()
            ->whereHas('cart', function ($q) use ($userId, $token): void {
                if ($userId !== null) {
                    $q->where('user_id', $userId);

                    return;
                }
                $q->where('guest_token', $token)->whereNull('user_id');
            })
            ->sum('quantity');
    }

    private static function whatsappUrl(?string $e164): string
    {
        if (! $e164) {
            return '#';
        }

        $digits = preg_replace('/\D+/', '', $e164);

        return $digits !== '' ? 'https://wa.me/'.$digits : '#';
    }

    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $supportWa = config('store.support_whatsapp_url') ?: self::whatsappUrl(config('store.support_phone'));

        $marketing = [
            'ga4_enabled' => false,
            'ga4_measurement_id' => null,
            'gtm_enabled' => false,
            'gtm_container_id' => null,
            'meta_pixel_enabled' => false,
            'meta_pixel_id' => null,
            'tiktok_pixel_enabled' => false,
            'tiktok_pixel_id' => null,
        ];
        if (Schema::hasTable('storefront_settings')) {
            $row = StorefrontSetting::query()->first();
            if ($row) {
                $marketing = $row->toAnalyticsPayload();
            }
        } elseif (Schema::hasTable('marketing_settings')) {
            $row = MarketingSetting::query()->first();
            if ($row) {
                $marketing = $row->toPublicPayload();
            }
        }

        $storeBranding = null;
        if (Schema::hasTable('storefront_settings')) {
            $row = StorefrontSetting::query()->first();
            if ($row) {
                $storeBranding = $row->toBrandingPayload();
            }
        }

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'status' => $request->session()->get('status'),
            ],
            'bargainEnabled' => (bool) config('bargain.enabled', true),
            'marketing' => $marketing,
            'storeBranding' => $storeBranding,
            'flashPaymentError' => $request->session()->pull('flash_payment_error'),
            'auth' => [
                'user' => $request->user(),
            ],
            'wishlistProductIds' => $request->user() && Schema::hasTable('wishlist_items')
                ? app(WishlistService::class)->productIdsForUser($request->user())
                : [],
            'cartCount' => $this->resolveCartCount($request),
            'locale' => $request->user()?->locale ?? config('app.locale', 'en'),
            'storefront' => [
                'support_phone' => config('store.support_phone'),
                'support_whatsapp_url' => $supportWa,
                'delivery_days_min' => config('store.delivery_days_min'),
                'delivery_days_max' => config('store.delivery_days_max'),
                'return_policy_summary' => config('store.return_policy_summary'),
                'surface_parent_slug' => config('store.surface_parent_slug'),
            ],
            'navCategories' => $this->navCategoryTree(),
        ];
    }

    /**
     * @return list<array{id: int, name: string, slug: string, sort_order: int, children: list<array{id: int, name: string, slug: string, sort_order: int}>}>
     */
    private function navCategoryTree(): array
    {
        if (! Schema::hasTable('categories')) {
            return [];
        }

        return Category::query()
            ->active()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with([
                'children' => fn ($q) => $q->active()->orderBy('sort_order'),
            ])
            ->get(['id', 'name', 'slug', 'sort_order'])
            ->map(fn (Category $parent) => [
                'id' => $parent->id,
                'name' => $parent->name,
                'slug' => $parent->slug,
                'sort_order' => (int) $parent->sort_order,
                'children' => $parent->children->map(fn (Category $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'sort_order' => (int) $child->sort_order,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
