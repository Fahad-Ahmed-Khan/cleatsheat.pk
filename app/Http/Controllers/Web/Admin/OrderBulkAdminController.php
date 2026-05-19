<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Admin\Orders\OrderAuditLogger;
use App\Domain\Admin\Orders\OrderPrintService;
use App\Domain\Payments\PaymentStatusRecorder;
use App\Domain\Shipping\CourierDispatchService;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkBookShipmentsRequest;
use App\Http\Requests\Admin\BulkPrintLabelsRequest;
use App\Http\Requests\Admin\BulkPrintPackingSlipsRequest;
use App\Http\Requests\Admin\BulkSyncTrackingRequest;
use App\Http\Requests\Admin\BulkUpdateOrderStatusRequest;
use App\Http\Requests\Admin\BulkUpdatePaymentStatusRequest;
use App\Jobs\BookShipmentJob;
use App\Jobs\SyncShipmentTrackingJob;
use App\Models\Courier;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;

class OrderBulkAdminController extends Controller
{
    public function __construct(
        private readonly CourierDispatchService $dispatch,
        private readonly PaymentStatusRecorder $paymentStatusRecorder,
        private readonly OrderPrintService $print,
        private readonly OrderAuditLogger $audit,
    ) {}

    public function book(BulkBookShipmentsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $orderIds = $data['order_ids'];
        $courierId = $data['courier_id'] ?? null;
        $mode = $data['mode']; // auto|manual

        $manualCourier = null;
        if ($mode === 'manual') {
            /** @var Courier|null $manualCourier */
            $manualCourier = Courier::query()->active()->whereKey($courierId)->first();
            if ($manualCourier === null) {
                return back()->with('error', 'Selected courier is not active.');
            }
        }

        $booked = 0;
        $skipped = [];

        /** @var Collection<int, Order> $orders */
        $orders = Order::query()
            ->whereIn('id', $orderIds)
            ->with(['shipments'])
            ->get();

        foreach ($orders as $order) {
            $ship = is_array($order->shipping_address_snapshot) ? $order->shipping_address_snapshot : [];
            $missing = [];
            foreach (['full_name', 'phone', 'line1', 'city'] as $k) {
                if (trim((string) ($ship[$k] ?? '')) === '') {
                    $missing[] = $k;
                }
            }
            if (! empty($missing)) {
                $skipped[] = [
                    'order_id' => $order->id,
                    'reason' => 'Missing shipping fields: '.implode(', ', $missing),
                ];

                continue;
            }

            $resolvedCourierId = $mode === 'manual'
                ? $manualCourier?->id
                : $this->dispatch->resolveDefaultCourierId($order);

            if ($resolvedCourierId === null) {
                $skipped[] = [
                    'order_id' => $order->id,
                    'reason' => 'No active courier available (configure a default courier or set manual courier).',
                ];

                continue;
            }

            /** @var Courier|null $courier */
            $courier = $mode === 'manual'
                ? $manualCourier
                : Courier::query()->active()->whereKey($resolvedCourierId)->first();

            if ($courier === null) {
                $skipped[] = [
                    'order_id' => $order->id,
                    'reason' => 'Resolved courier is not active.',
                ];

                continue;
            }

            $shipment = $this->dispatch->ensurePendingShipmentWithCourier($order, $courier);
            if ($courier->adapter !== 'generic' && $courier->adapter !== '' && $shipment->courier_account_id === null) {
                $skipped[] = [
                    'order_id' => $order->id,
                    'reason' => 'No API account for '.$courier->name.' (for COD, enable COD on the courier account in Shipping settings).',
                ];

                continue;
            }

            BookShipmentJob::dispatch($shipment->id);
            $booked++;
        }

        return back()->with('status', "Queued booking for {$booked} order(s). Skipped ".count($skipped).'.')
            ->with('bulk_summary', [
                'booked_count' => $booked,
                'skipped_count' => count($skipped),
                'skipped' => $skipped,
            ]);
    }

    public function syncTracking(BulkSyncTrackingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $orderIds = $data['order_ids'];

        $queued = 0;
        $orders = Order::query()->whereIn('id', $orderIds)->with('shipments')->get();

        foreach ($orders as $order) {
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
        }

        $msg = $queued > 0
            ? "Tracking sync queued for {$queued} shipment(s)."
            : 'No shipments with a tracking number to sync.';

        return back()->with('status', $msg);
    }

    public function updateStatus(BulkUpdateOrderStatusRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $orderIds = $data['order_ids'];
        $toStatus = OrderStatus::from($data['status']);

        $updated = 0;
        $orders = Order::query()->whereIn('id', $orderIds)->get();

        foreach ($orders as $order) {
            if ($order->status === $toStatus) {
                continue;
            }

            $changes = ['status' => ['from' => $order->status->value, 'to' => $toStatus->value]];
            $order->status = $toStatus;
            $order->save();

            $this->audit->log(
                $order,
                'bulk_status_update',
                $request->user(),
                $changes,
                ['payload' => ['status' => $toStatus->value]],
            );
            $updated++;
        }

        return back()->with('status', "Updated {$updated} order(s).");
    }

    /**
     * Bulk-transition payment_status with explicit guardrails for destructive moves.
     *
     * - Non-destructive transitions (pending / failed) flow through PaymentStatusRecorder
     *   without `force` so duplicate writes are no-ops.
     * - Destructive transitions (paid / refunded / canceled) require `override=true` and
     *   a non-empty `reason`; both are recorded in the payment history `meta` and the
     *   order audit log to keep reconciliation evidence intact.
     */
    public function updatePaymentStatus(BulkUpdatePaymentStatusRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $orderIds = $data['order_ids'];
        $toPaymentStatus = PaymentStatus::from($data['payment_status']);
        $override = (bool) ($data['override'] ?? false);
        $reason = trim((string) ($data['reason'] ?? ''));
        $isDestructive = in_array(
            $toPaymentStatus->value,
            BulkUpdatePaymentStatusRequest::DESTRUCTIVE_TRANSITIONS,
            true,
        );

        $updated = 0;
        $orders = Order::query()->whereIn('id', $orderIds)->get();

        foreach ($orders as $order) {
            if ($order->payment_status === $toPaymentStatus) {
                continue;
            }

            $changes = [
                'payment_status' => [
                    'from' => $order->payment_status->value,
                    'to' => $toPaymentStatus->value,
                ],
            ];

            $this->paymentStatusRecorder->transitionOrderPayment(
                $order,
                $toPaymentStatus,
                'admin_bulk',
                payment: null,
                message: $reason !== '' ? $reason : 'Admin bulk payment update',
                meta: [
                    'order_id' => $order->id,
                    'override' => $isDestructive ? $override : false,
                    'reason' => $reason,
                    'actor_user_id' => $request->user()?->id,
                ],
                force: $isDestructive ? $override : false,
            );

            $order->save();

            $this->audit->log(
                $order,
                'bulk_payment_status_update',
                $request->user(),
                $changes,
                [
                    'payload' => [
                        'payment_status' => $toPaymentStatus->value,
                        'override' => $isDestructive ? $override : false,
                        'reason' => $reason,
                    ],
                ],
            );
            $updated++;
        }

        return back()->with('status', "Updated payment status on {$updated} order(s).");
    }

    /**
     * Generate a single PDF with 1 label per page.
     */
    public function printLabels(BulkPrintLabelsRequest $request): HttpResponse
    {
        $data = $request->validated();
        $layout = $data['layout'] ?? 'one_per_page';

        return $this->print->labelsPdf(
            $data['order_ids'],
            $data['paper_size'] ?? 'a6',
            $layout,
        );
    }

    /**
     * Generate a single PDF with 1 packing slip per page.
     */
    public function printPackingSlips(BulkPrintPackingSlipsRequest $request): HttpResponse
    {
        $data = $request->validated();

        return $this->print->packingSlipsPdf($data['order_ids']);
    }
}
