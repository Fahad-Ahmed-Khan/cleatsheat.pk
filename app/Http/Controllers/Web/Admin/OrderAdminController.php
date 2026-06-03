<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Notifications\WhatsApp\ManualMessageService;
use App\Domain\Shipping\CourierDispatchService;
use App\Domain\Shipping\PostEx\PostExApiClient;
use App\Domain\Shipping\PostEx\PostExHttpDiagnostics;
use App\Domain\Shipping\PostEx\PostExShipmentInspector;
use App\Domain\Shipping\PostEx\PostExTokenResolver;
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
use App\Models\ShippingSetting;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderAdminController extends Controller
{
    public function __construct(
        private readonly CourierDispatchService $dispatch,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->parseOrderFilters($request);
        $perPage = $this->resolvePerPage($request);

        $orders = $this->buildOrderQuery($filters)
            ->paginate($perPage)
            ->withQueryString();

        $orders->through(function (Order $o) {
            $o->created_at_human = $o->created_at?->format('M j, Y H:i');
            $ship = is_array($o->shipping_address_snapshot) ? $o->shipping_address_snapshot : [];
            // Prefer the recipient name typed at checkout (the shipping snapshot) over the
            // logged-in account's profile name — staff placing orders for customers would
            // otherwise show up as themselves instead of the actual recipient.
            $o->customer_name = (string) ($ship['full_name'] ?? $o->user?->name ?? 'Guest');
            $o->customer_phone = (string) ($ship['phone'] ?? '');

            return $o;
        });

        $couriers = Courier::query()->active()->orderBy('sort_order')->orderBy('name')->get([
            'id', 'code', 'name', 'adapter',
        ]);

        $paymentGateways = Order::query()
            ->whereNotNull('payment_gateway')
            ->where('payment_gateway', '!=', '')
            ->distinct()
            ->pluck('payment_gateway')
            ->filter()
            ->values()
            ->all();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'couriers' => $couriers,
            'payment_gateways' => $paymentGateways,
            'filters' => array_merge($filters, ['per_page' => $perPage]),
            'stats' => [
                'pending_payment' => Order::query()->where('payment_status', PaymentStatus::Pending)->count(),
                'completed' => Order::query()->where('status', OrderStatus::Delivered)->count(),
                'refunded' => Order::query()->where('payment_status', PaymentStatus::Refunded)->count(),
                'failed' => Order::query()->where('payment_status', PaymentStatus::Failed)->count(),
            ],
        ]);
    }

    /**
     * Stream a CSV of the filtered orders. Reuses the same query builder as index()
     * so the file always matches what the admin currently sees in the table.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->parseOrderFilters($request);

        $filename = 'orders-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($filters): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, [
                'Order #',
                'Created at',
                'Customer name',
                'Customer email',
                'Customer phone',
                'Order status',
                'Payment status',
                'Payment gateway',
                'Delivery status',
                'Subtotal',
                'Discount',
                'Shipping',
                'COD fee',
                'Grand total',
                'City',
            ]);

            $this->buildOrderQuery($filters)
                ->with('user:id,email,name')
                ->chunk(500, function ($orders) use ($out): void {
                    foreach ($orders as $order) {
                        $ship = is_array($order->shipping_address_snapshot) ? $order->shipping_address_snapshot : [];
                        fputcsv($out, [
                            $order->order_number,
                            $order->created_at?->format('Y-m-d H:i:s'),
                            (string) ($ship['full_name'] ?? $order->user?->name ?? 'Guest'),
                            (string) ($order->user?->email ?? $order->guest_email ?? ''),
                            (string) ($ship['phone'] ?? ''),
                            $order->status->value,
                            $order->payment_status->value,
                            (string) ($order->payment_gateway ?? ''),
                            (string) ($order->delivery_status ?? ''),
                            (string) $order->subtotal,
                            (string) $order->discount_total,
                            (string) $order->shipping_total,
                            (string) $order->cod_fee,
                            (string) $order->grand_total,
                            (string) ($ship['city'] ?? ''),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Normalize the query parameters used by both the index and export endpoints.
     *
     * @return array<string, mixed>
     */
    private function parseOrderFilters(Request $request): array
    {
        $courierId = $request->input('courier_id');
        if ($courierId !== null && $courierId !== '') {
            $courierId = (int) $courierId;
        } else {
            $courierId = null;
        }

        return [
            'search' => trim((string) $request->input('search', '')),
            'status' => $request->input('status') ?: null,
            'payment_status' => $request->input('payment_status') ?: null,
            'payment_gateway' => $request->input('payment_gateway') ?: null,
            'delivery_status' => $request->input('delivery_status') ?: null,
            'courier_id' => $courierId,
            'date_from' => $request->input('date_from') ?: null,
            'date_to' => $request->input('date_to') ?: null,
            'preset' => $request->input('preset') ?: null,
        ];
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 25);
        if ($perPage <= 0) {
            $perPage = 25;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        return $perPage;
    }

    /**
     * Shared query builder used by index() and export().
     *
     * @param  array<string, mixed>  $filters
     */
    private function buildOrderQuery(array $filters)
    {
        $search = (string) ($filters['search'] ?? '');
        $status = $filters['status'] ?? null;
        $paymentStatus = $filters['payment_status'] ?? null;
        $paymentGateway = $filters['payment_gateway'] ?? null;
        $deliveryStatus = $filters['delivery_status'] ?? null;
        $courierId = $filters['courier_id'] ?? null;
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $preset = $filters['preset'] ?? null;

        $query = Order::query()
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
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($paymentStatus, fn ($q) => $q->where('payment_status', $paymentStatus))
            ->when($paymentGateway, function ($q) use ($paymentGateway) {
                if ($paymentGateway === '__cod__') {
                    // COD-like gateways are stored as variants such as `cod`, `cash_on_delivery`.
                    $q->where(function ($qq) {
                        $qq->where('payment_gateway', 'like', '%cod%')
                            ->orWhere('payment_gateway', 'like', '%cash%');
                    });
                } elseif ($paymentGateway === '__prepaid__') {
                    $q->whereNotNull('payment_gateway')
                        ->where('payment_gateway', '!=', '')
                        ->where('payment_gateway', 'not like', '%cod%')
                        ->where('payment_gateway', 'not like', '%cash%');
                } else {
                    $q->where('payment_gateway', $paymentGateway);
                }
            })
            ->when($courierId, function ($q) use ($courierId) {
                $q->whereHas('shipments', fn ($sq) => $sq->where('courier_id', $courierId));
            })
            ->when($deliveryStatus, function ($q) use ($deliveryStatus) {
                $q->whereHas('shipments', fn ($sq) => $sq->where('status', $deliveryStatus));
            })
            ->when($dateFrom, function ($q) use ($dateFrom) {
                try {
                    $q->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
                } catch (\Throwable) {
                    // ignore malformed date
                }
            })
            ->when($dateTo, function ($q) use ($dateTo) {
                try {
                    $q->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
                } catch (\Throwable) {
                    // ignore malformed date
                }
            });

        match ($preset) {
            'today' => $query->whereDate('created_at', now()->toDateString()),
            'today_unbooked' => $query
                ->whereDate('created_at', now()->toDateString())
                ->where(function ($q) {
                    $q->whereDoesntHave('shipments')
                        ->orWhereDoesntHave('shipments', fn ($sq) => $sq->whereNotIn('status', [
                            ShipmentStatus::Pending->value,
                            ShipmentStatus::Failed->value,
                        ]));
                }),
            'pending_payment_24h' => $query
                ->where('payment_status', PaymentStatus::Pending)
                ->where('created_at', '<=', now()->subDay()),
            'booking_failed' => $query->whereHas(
                'shipments',
                fn ($sq) => $sq->where('status', ShipmentStatus::Failed->value),
            ),
            default => null,
        };

        return $query->latest();
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

        $manual = app(ManualMessageService::class);

        return Inertia::render('Admin/Orders/Show', [
            'whatsapp_templates' => $manual->customerTemplateOptions(),
            'whatsapp_send_route' => route('admin.orders.whatsapp.send', $order),
            'whatsapp_confirmation' => [
                'awaiting' => (bool) $order->awaiting_confirmation,
                'sent_at' => $order->confirmation_sent_at?->format('M j, H:i'),
                'confirmed_at' => $order->confirmed_at?->format('M j, H:i'),
                'channel' => $order->confirmation_channel,
            ],
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
                        'image_url' => \App\Support\Storage\PublicAssetUrl::resolve($img?->path),
                        'image_alt' => $img?->alt,
                    ];
                })->values()->all(),
                'payments' => $order->payments
                    ->sortByDesc('created_at')
                    ->values()
                    ->map(fn ($p) => [
                        'id' => $p->id,
                        'gateway' => $p->gateway,
                        'status' => $p->status?->value,
                        'amount' => (float) $p->amount,
                        'external_id' => $p->external_id,
                        'paid_at' => $p->paid_at?->toIso8601String(),
                        'created_at' => $p->created_at?->toIso8601String(),
                        'meta' => $p->meta,
                    ])->all(),
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

        $shipment->load('courier');
        if ($courier->adapter !== 'generic' && $courier->adapter !== '' && $shipment->courier_account_id === null) {
            return redirect()
                ->route('admin.orders.show', $order)
                ->with('error', 'No courier API account is available for '.$courier->name.' on this order (COD orders need an account with COD enabled). Fix Admin → Shipping settings, then book again.');
        }

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
            // Operator-initiated sync — force=true lets the service reconcile shipments
            // that already sit in terminal Failed state (e.g. cancelled on the courier).
            SyncShipmentTrackingJob::dispatch($shipment->id, true);
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

        $token = PostExTokenResolver::forCourierAccount($shipment->courierAccount);
        abort_if($token === '', 422, 'PostEx API token is not configured. Add it under Admin → Shipping settings for the PostEx courier account.');

        if (PostExShipmentInspector::isAppSandboxBooking($shipment)) {
            abort(422, 'This shipment uses a local sandbox tracking number (no order exists in PostEx). Set SHIPPING_SANDBOX=false in .env, run php artisan config:clear, then book again (or create the shipment in PostEx manually).');
        }

        $base = PostExApiClient::resolvedBaseUrl();
        $url = $base.'/services/integration/api/order/v1/getinvoice';
        $tracking = (string) $shipment->tracking_number;

        $res = Http::retry(3, 250, null, false)
            ->timeout(45)
            ->withHeaders(['token' => $token])
            ->get($url, ['trackingNumbers' => $tracking]);

        if ($res->status() === 404) {
            abort(404, 'PostEx no longer has this shipment (tracking '.$tracking.'). It was likely cancelled on the PostEx portal. Go back to the order and click "Sync tracking" to refresh the local status.');
        }

        abort_unless($res->successful(), 502, 'PostEx invoice PDF request failed. '.PostExHttpDiagnostics::summarizeFailedResponse($res));

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

        foreach ($shipments as $s) {
            if (PostExShipmentInspector::isAppSandboxBooking($s)) {
                abort(422, 'This order includes a local sandbox PostEx shipment (no orders in PostEx). Set SHIPPING_SANDBOX=false, clear config, then book again before using the load sheet.');
            }
        }

        /** @var Shipment $first */
        $first = $shipments->first();
        $token = PostExTokenResolver::forCourierAccount($first->courierAccount);
        abort_if($token === '', 422, 'PostEx API token is not configured. Add it under Admin → Shipping settings for the PostEx courier account.');

        $settings = ShippingSetting::current();
        $pickup = trim((string) ($settings->postex_pickup_address_code ?? ''));
        if ($pickup === '') {
            abort(422, 'PostEx load sheet requires a pickup address. Set “Pickup address code” under Admin → Shipping → PostEx defaults (same value used when booking).');
        }

        $trackingNumbers = $shipments->pluck('tracking_number')->filter()->unique()->values()->take(10)->all();

        $base = PostExApiClient::resolvedBaseUrl();
        $url = $base.'/services/integration/api/order/v2/generate-load-sheet';
        $payload = [
            'pickupAddress' => $pickup,
            'trackingNumbers' => $trackingNumbers,
        ];

        $res = Http::retry(3, 250, null, false)
            ->timeout(45)
            ->withHeaders(['token' => $token])
            ->accept('application/pdf')
            ->asJson()
            ->post($url, $payload);

        if ($res->status() === 404) {
            abort(404, 'PostEx does not recognise one or more of these tracking numbers ('.implode(', ', $trackingNumbers).'). They may have been cancelled on the PostEx portal. Sync tracking on the affected shipments and try again.');
        }

        abort_unless($res->successful(), 502, 'PostEx load sheet PDF request failed. '.PostExHttpDiagnostics::summarizeFailedResponse($res));

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

        if (PostExShipmentInspector::isAppSandboxBooking($shipment)) {
            return redirect()->route('admin.orders.show', $order)->with('error', 'This shipment uses a local sandbox tracking number (nothing to cancel in PostEx). Set SHIPPING_SANDBOX=false, clear config, then book again.');
        }

        $token = PostExTokenResolver::forCourierAccount($shipment->courierAccount);
        if ($token === '') {
            return redirect()->route('admin.orders.show', $order)->with('error', 'PostEx API token is not configured. Add it under Admin → Shipping settings for the PostEx courier account.');
        }

        $base = PostExApiClient::resolvedBaseUrl();
        $url = $base.'/services/integration/api/order/v1/cancel-order';
        $payload = ['trackingNumber' => (string) $shipment->tracking_number];

        $res = Http::retry(3, 250, null, false)
            ->timeout(30)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['token' => $token])
            ->put($url, $payload);

        $body = $res->json() ?: [];
        if (! $res->successful()) {
            return redirect()
                ->route('admin.orders.show', $order)
                ->with('error', 'PostEx cancel failed. '.PostExHttpDiagnostics::summarizeFailedResponse($res));
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
