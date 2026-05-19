<?php

namespace App\Jobs;

use App\Domain\Payments\PaymentStatusRecorder;
use App\Enums\PaymentStatus;
use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Reconcile order payment status for delivered COD shipments.
 *
 * Two modes:
 *   - Single-shipment mode (constructed with a shipmentId): used by the
 *     courier webhook handler when a delivery event lands.
 *   - Sweep mode (no shipmentId): runs on a schedule and catches any COD
 *     deliveries we missed for whatever reason (webhook downtime, etc.).
 *
 * Either way, we only auto-mark orders as paid when:
 *   - the order's current payment_status is NOT already paid,
 *   - the shipment is marked as delivered, and
 *   - the order's payment_gateway is COD-like.
 * No `force` is used; the recorder writes a clean transition with a
 * `courier_webhook` source so the audit trail is unambiguous.
 */
class ReconcileCodFromCourierWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $shipmentId = null,
        public int $maxBatch = 50,
    ) {
        $this->onQueue('default');
    }

    public function handle(PaymentStatusRecorder $recorder): void
    {
        $query = Shipment::query()
            ->with('order')
            ->where('status', ShipmentStatus::Delivered);

        if ($this->shipmentId !== null) {
            $query->where('id', $this->shipmentId);
        } else {
            $query->where('delivered_at', '>=', now()->subDays(7))
                ->limit($this->maxBatch);
        }

        $reconciled = 0;

        foreach ($query->get() as $shipment) {
            $order = $shipment->order;
            if ($order === null) {
                continue;
            }

            if ($order->payment_status === PaymentStatus::Paid) {
                continue;
            }

            $gateway = strtolower((string) ($order->payment_gateway ?? ''));
            $isCod = str_contains($gateway, 'cod') || str_contains($gateway, 'cash');
            if (! $isCod) {
                continue;
            }

            $recorder->transitionOrderPayment(
                $order,
                PaymentStatus::Paid,
                'courier_webhook',
                payment: null,
                message: 'COD reconciled from courier delivery event',
                meta: [
                    'shipment_id' => $shipment->id,
                    'tracking_number' => $shipment->tracking_number,
                    'delivered_at' => $shipment->delivered_at?->toIso8601String(),
                ],
                force: false,
            );

            $reconciled++;
        }

        if ($reconciled > 0) {
            Log::info('payments.cod_reconciled_from_delivery', [
                'count' => $reconciled,
                'mode' => $this->shipmentId === null ? 'sweep' : 'single',
            ]);
        }
    }
}
