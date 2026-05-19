<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Marketing\AbandonedCartReminderService;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AbandonedCartAdminController extends Controller
{
    public function __construct(
        private readonly AbandonedCartReminderService $reminders,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $query = Cart::query()
            ->has('items')
            ->with([
                'user',
                'items' => function ($q): void {
                    $q->with(['variant.product', 'variant.color']);
                },
            ])
            ->withCount('items');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($w) use ($like): void {
                $w->where('guest_token', 'like', $like)
                    ->orWhereHas('user', function ($u) use ($like): void {
                        $u->where('email', 'like', $like)
                            ->orWhere('name', 'like', $like);
                    });
            });
        }

        $carts = $query
            ->orderByDesc('updated_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Cart $cart): array => $this->serializeCart($cart));

        return Inertia::render('Admin/AbandonedCarts/Index', [
            'carts' => $carts,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function sendReminder(Cart $cart): RedirectResponse
    {
        $outcome = $this->reminders->send($cart);

        return match ($outcome) {
            'sent' => back()->with('status', 'WhatsApp reminder queued.'),
            'no_phone' => back()->with('error', 'No phone number on file for this cart\'s account.'),
            default => back()->with('error', 'WhatsApp reminder failed (see notification logs).'),
        };
    }

    public function bulkSendReminder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cart_ids' => ['required', 'array', 'min:1'],
            'cart_ids.*' => ['integer', 'distinct', 'exists:carts,id'],
        ]);

        $sent = 0;
        $skipped = [];

        $carts = Cart::query()
            ->whereIn('id', $data['cart_ids'])
            ->with(['user', 'items.variant.product'])
            ->get();

        foreach ($carts as $cart) {
            $outcome = $this->reminders->send($cart);
            if ($outcome === 'sent') {
                $sent++;

                continue;
            }

            $skipped[] = [
                'order_id' => $cart->id,
                'reason' => $outcome === 'no_phone'
                    ? 'No phone on the customer account.'
                    : 'WhatsApp dispatch failed (see notification logs).',
            ];
        }

        return back()
            ->with('status', "Queued {$sent} reminder(s). Skipped ".count($skipped).'.')
            ->with('bulk_summary', [
                'booked_count' => $sent,
                'skipped_count' => count($skipped),
                'skipped' => $skipped,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCart(Cart $cart): array
    {
        $lines = $cart->items->map(function (CartItem $i): array {
            $unit = (float) $i->unit_price_snapshot;

            return [
                'product_name' => $i->variant?->product?->name,
                'variant_label' => $i->variant?->color?->name,
                'sku' => $i->variant?->sku,
                'size_label' => $i->size_label,
                'quantity' => $i->quantity,
                'unit_price' => (string) $i->unit_price_snapshot,
                'line_total' => number_format($unit * $i->quantity, 2, '.', ''),
            ];
        });

        $subtotal = $cart->items->sum(fn (CartItem $i) => (float) $i->unit_price_snapshot * $i->quantity);

        $phone = trim((string) ($cart->user?->phone ?? ''));

        return [
            'id' => $cart->id,
            'account_label' => $cart->user !== null
                ? (string) ($cart->user->email ?? $cart->user->name ?? 'User #'.$cart->user_id)
                : 'Guest',
            'guest_token_short' => $cart->guest_token !== null
                ? substr((string) $cart->guest_token, 0, 8).'…'
                : null,
            'user_id' => $cart->user_id,
            'phone' => $phone !== '' ? $phone : null,
            'is_reachable' => $phone !== '',
            'items_count' => (int) $cart->items_count,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'currency' => $cart->currency ?? 'PKR',
            'updated_at' => $cart->updated_at?->toIso8601String(),
            'lines' => $lines->values()->all(),
        ];
    }
}
