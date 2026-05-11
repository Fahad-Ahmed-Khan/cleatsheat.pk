<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Shipping\CourierDispatchService;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderRequest;
use App\Jobs\BookShipmentJob;
use App\Jobs\SyncShipmentTrackingJob;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class OrderAdminController extends Controller
{
    public function __construct(
        private readonly CourierDispatchService $dispatch,
    ) {}

    public function index(): Response
    {
        $search = trim((string) request('search', ''));
        $status = request('status'); // order status
        $paymentStatus = request('payment_status');
        $perPage = (int) request('per_page', 25);
        if ($perPage <= 0) $perPage = 25;
        if ($perPage > 100) $perPage = 100;

        $orders = Order::query()
            ->with('user:id,email,name')
            ->addSelect([
                'delivery_status' => Shipment::query()
                    ->select('status')
                    ->whereColumn('order_id', 'orders.id')
                    ->orderByDesc('id')
                    ->limit(1),
            ])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('order_number', 'like', "%{$search}%")
                        ->orWhere('guest_email', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('email', 'like', "%{$search}%"));
                });
            })
            ->when($status !== null && $status !== '', fn ($q) => $q->where('status', $status))
            ->when($paymentStatus !== null && $paymentStatus !== '', fn ($q) => $q->where('payment_status', $paymentStatus))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $orders->through(function (Order $o) {
            $o->created_at_human = $o->created_at?->format('M j, Y H:i');
            $ship = is_array($o->shipping_address_snapshot) ? $o->shipping_address_snapshot : [];
            $o->customer_name = (string) ($o->user?->name ?? $ship['full_name'] ?? 'Guest');
            $o->customer_phone = (string) ($ship['phone'] ?? '');
            return $o;
        });

        $couriers = Courier::query()->active()->orderBy('sort_order')->orderBy('name')->get([
            'id', 'code', 'name', 'adapter',
        ]);

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'couriers' => $couriers,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'per_page' => $perPage,
            ],
            'stats' => [
                'pending_payment' => Order::query()->where('payment_status', PaymentStatus::Pending)->count(),
                'completed' => Order::query()->where('status', OrderStatus::Delivered)->count(),
                'refunded' => Order::query()->where('payment_status', PaymentStatus::Refunded)->count(),
                'failed' => Order::query()->where('payment_status', PaymentStatus::Failed)->count(),
            ],
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load([
            'items.variant.product.images', 'user', 'payments',
            'adjustments',
            'returns.items',
            'shipments.courier',
            'shipments.courierAccount',
            'shipments.events' => fn ($q) => $q->orderByDesc('occurred_at')->orderByDesc('id')->limit(50),
        ]);

        $couriers = Courier::query()->active()->orderBy('sort_order')->orderBy('name')->get([
            'id', 'code', 'name', 'adapter',
        ]);

        $latestPending = $order->shipments
            ->firstWhere('status', ShipmentStatus::Pending);

        $defaultBookingCourierId = $latestPending?->courier_id
            ?? $this->dispatch->resolveDefaultCourierId($order);

        return Inertia::render('Admin/Orders/Show', [
            'order_statuses' => collect(OrderStatus::cases())->map(fn (OrderStatus $s) => [
                'value' => $s->value,
                'label' => Str::headline($s->name),
            ])->values()->all(),
            'payment_statuses' => collect(PaymentStatus::cases())->map(fn (PaymentStatus $s) => [
                'value' => $s->value,
                'label' => Str::headline($s->name),
            ])->values()->all(),
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'payment_gateway' => $order->payment_gateway,
                'guest_email' => $order->guest_email,
                'user' => $order->user ? ['email' => $order->user->email, 'name' => $order->user->name] : null,
                'customer_phone' => (string) ($order->shipping_address_snapshot['phone'] ?? ''),
                'customer_name' => (string) ($order->shipping_address_snapshot['full_name'] ?? ($order->user?->name ?? 'Guest')),
                'subtotal' => (float) $order->subtotal,
                'discount_total' => (float) $order->discount_total,
                'shipping_total' => (float) $order->shipping_total,
                'cod_fee' => (float) $order->cod_fee,
                'grand_total' => (float) $order->grand_total,
                'shipping_address_snapshot' => $order->shipping_address_snapshot,
                'customer_notes' => $order->customer_notes,
                'preferred_courier_id' => $order->preferred_courier_id,
                'courier_assignment' => $order->courier_assignment->value,
                'items' => $order->items->map(function ($it) {
                    $img = $it->variant?->product?->images?->first();

                    return [
                        'id' => $it->id,
                        'product_variant_id' => $it->product_variant_id,
                        'product_name' => $it->product_name,
                        'variant_label' => $it->variant_label,
                        'size_label' => $it->size_label,
                        'sku' => $it->sku,
                        'quantity' => (int) $it->quantity,
                        'unit_price' => (float) $it->unit_price,
                        'line_total' => (float) $it->line_total,
                        'image_url' => $img?->path,
                        'image_alt' => $img?->alt,
                    ];
                })->values()->all(),
                'payments' => $order->payments,
                'admin_discount' => $order->adjustments
                    ->whereNull('voided_at')
                    ->sortByDesc('id')
                    ->map(fn ($a) => [
                        'id' => $a->id,
                        'type' => $a->type,
                        'value' => (float) $a->value,
                        'reason' => $a->reason,
                        'created_at' => $a->created_at?->toIso8601String(),
                    ])->first(),
                'returns' => $order->returns->map(fn ($r) => [
                    'id' => $r->id,
                    'reason' => $r->reason,
                    'restock' => (bool) $r->restock,
                    'created_at' => $r->created_at?->toIso8601String(),
                    'items' => $r->items->map(fn ($ri) => [
                        'order_item_id' => $ri->order_item_id,
                        'qty' => (int) $ri->qty,
                    ])->values()->all(),
                ])->values()->all(),
                'shipments' => $order->shipments->map(fn ($s) => [
                    'id' => $s->id,
                    'tracking_number' => $s->tracking_number,
                    'booking_reference' => $s->booking_reference,
                    'status' => $s->status->value,
                    'cod_amount' => $s->cod_amount !== null ? (float) $s->cod_amount : null,
                    'courier' => $s->courier ? [
                        'id' => $s->courier->id,
                        'name' => $s->courier->name,
                        'adapter' => $s->courier->adapter,
                        'code' => $s->courier->code,
                    ] : null,
                    'courier_account_id' => $s->courier_account_id,
                    'label_url' => $s->label_url,
                    'invoice_url' => $s->invoice_url,
                    'meta' => $s->meta,
                    'events' => $s->events->map(fn ($e) => [
                        'status' => $e->status,
                        'description' => $e->description,
                        'occurred_at' => $e->occurred_at?->toIso8601String(),
                    ]),
                ]),
                'created_at' => $order->created_at->toIso8601String(),
                'created_at_human' => $order->created_at->format('M j, Y H:i'),
            ],
            'couriers' => $couriers,
            'defaultBookingCourierId' => $defaultBookingCourierId,
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $order->update($request->validated());

        return redirect()->route('admin.orders.show', $order)->with('status', 'Order updated');
    }

    /**
     * Book the order's pending shipment with the chosen courier.
     * Falls back to the resolved default courier when none is provided.
     */
    public function book(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'courier_id' => ['nullable', 'integer', 'exists:couriers,id'],
        ]);

        $courierId = $data['courier_id']
            ?? $this->dispatch->resolveDefaultCourierId($order);

        if ($courierId === null) {
            return redirect()
                ->route('admin.orders.show', $order)
                ->with('error', 'No active courier available. Configure one in Couriers settings first.');
        }

        $courier = Courier::query()->active()->whereKey($courierId)->first();
        if ($courier === null) {
            return redirect()
                ->route('admin.orders.show', $order)
                ->with('error', 'Selected courier is not active.');
        }

        $shipment = $this->dispatch->ensurePendingShipmentWithCourier($order, $courier);

        BookShipmentJob::dispatch($shipment->id);

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', 'Booking queued with '.$courier->name.'.');
    }

    /**
     * Queue a tracking sync for every shipment on this order that has a tracking
     * number. Useful when the courier's webhook is missing or delayed.
     */
    public function syncTracking(Order $order): RedirectResponse
    {
        $order->load('shipments');

        $queued = 0;
        foreach ($order->shipments as $shipment) {
            if (! in_array($shipment->status, [
                ShipmentStatus::Booked,
                ShipmentStatus::InTransit,
                ShipmentStatus::Failed,
            ], true)) {
                continue;
            }
            if ((string) $shipment->tracking_number === '') {
                continue;
            }
            SyncShipmentTrackingJob::dispatch($shipment->id);
            $queued++;
        }

        $msg = $queued > 0
            ? "Tracking sync queued for {$queued} shipment(s)."
            : 'No shipments with a tracking number to sync.';

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', $msg);
    }

    public function postExInvoice(Order $order, Shipment $shipment): HttpResponse
    {
        abort_unless($shipment->order_id === $order->id, 404);

        $shipment->load(['courier', 'courierAccount']);
        abort_unless($shipment->courier?->adapter === 'postex', 404);
        abort_unless((string) $shipment->tracking_number !== '', 400);

        $token = (string) ($shipment->courierAccount?->credentials['api_token'] ?? '');
        abort_if($token === '', 422, 'PostEx token is missing for this shipment’s courier account.');

        $base = rtrim((string) config('shipping.endpoints.postex'), '/');
        $url = $base.'/services/integration/api/order/v1/getinvoice';
        $tracking = (string) $shipment->tracking_number;

        $res = Http::retry(3, 250)
            ->timeout(45)
            ->withHeaders(['token' => $token])
            ->get($url, ['trackingNumbers' => $tracking]);

        abort_unless($res->successful(), 502, 'PostEx invoice PDF request failed.');

        return response($res->body(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="postex-invoice-'.$tracking.'.pdf"',
        ]);
    }

    public function postExLoadSheet(Order $order): HttpResponse
    {
        $order->load(['shipments.courier', 'shipments.courierAccount']);

        $shipments = $order->shipments
            ->filter(fn (Shipment $s) => $s->courier?->adapter === 'postex')
            ->filter(fn (Shipment $s) => (string) $s->tracking_number !== '')
            ->values();

        abort_if($shipments->isEmpty(), 422, 'No PostEx shipments with tracking numbers found on this order.');

        /** @var Shipment $first */
        $first = $shipments->first();
        $token = (string) ($first->courierAccount?->credentials['api_token'] ?? '');
        abort_if($token === '', 422, 'PostEx token is missing for this order’s PostEx courier account.');

        $trackingNumbers = $shipments->pluck('tracking_number')->take(50)->values()->all();

        $base = rtrim((string) config('shipping.endpoints.postex'), '/');
        $url = $base.'/services/integration/api/order/v2/generate-load-sheet';
        $payload = [
            'pickupAddress' => null,
            'trackingNumbers' => $trackingNumbers,
        ];

        $res = Http::retry(3, 250)
            ->timeout(45)
            ->withHeaders(['token' => $token])
            ->accept('application/pdf')
            ->asJson()
            ->post($url, $payload);

        abort_unless($res->successful(), 502, 'PostEx load sheet PDF request failed.');

        return response($res->body(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="postex-load-sheet-'.$order->order_number.'.pdf"',
        ]);
    }

    public function postExCancel(Order $order, Shipment $shipment): RedirectResponse
    {
        abort_unless($shipment->order_id === $order->id, 404);

        $shipment->load(['courier', 'courierAccount']);
        if ($shipment->courier?->adapter !== 'postex') {
            return redirect()->route('admin.orders.show', $order)->with('error', 'This shipment is not PostEx.');
        }
        if ((string) $shipment->tracking_number === '') {
            return redirect()->route('admin.orders.show', $order)->with('error', 'No tracking number found to cancel.');
        }
        if ($shipment->status === ShipmentStatus::Canceled) {
            return redirect()->route('admin.orders.show', $order)->with('status', 'Shipment is already canceled.');
        }

        $token = (string) ($shipment->courierAccount?->credentials['api_token'] ?? '');
        if ($token === '') {
            return redirect()->route('admin.orders.show', $order)->with('error', 'PostEx token is missing for this shipment’s courier account.');
        }

        $base = rtrim((string) config('shipping.endpoints.postex'), '/');
        $url = $base.'/services/integration/api/order/v1/cancel-order';
        $payload = ['trackingNumber' => (string) $shipment->tracking_number];

        $res = Http::retry(3, 250)
            ->timeout(30)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['token' => $token])
            ->put($url, $payload);

        $body = $res->json() ?: [];
        if (! $res->successful()) {
            return redirect()
                ->route('admin.orders.show', $order)
                ->with('error', (string) ($body['statusMessage'] ?? 'PostEx cancel request failed.'));
        }

        $shipment->status = ShipmentStatus::Canceled;
        $shipment->meta = array_merge($shipment->meta ?? [], [
            'postex_cancel_response' => $body,
        ]);
        $shipment->save();

        ShipmentEvent::query()->create([
            'shipment_id' => $shipment->id,
            'status' => ShipmentStatus::Canceled->value,
            'description' => 'Shipment canceled on PostEx',
            'raw_payload' => $body,
            'occurred_at' => now(),
        ]);

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', 'PostEx cancel queued/sent.');
    }
}
