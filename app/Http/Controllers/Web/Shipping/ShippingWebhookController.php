<?php

namespace App\Http\Controllers\Web\Shipping;

use App\Domain\Notifications\WhatsApp\ShipmentStatusCustomerAlertService;
use App\Domain\Shipping\Trax\TraxStatusMapper;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SyncShipmentTrackingJob;
use App\Models\CourierApiLog;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

/**
 * Generic webhook sink — logs payloads and triggers a tracking refresh when a shipment matches `tracking_number`.
 */
class ShippingWebhookController extends Controller
{
    public function __construct(
        private readonly ShipmentStatusCustomerAlertService $customerAlerts,
    ) {}

    public function handle(Request $request, string $courier): Response
    {
        $courier = strtolower($courier);
        $payload = $request->all();

        $secret = (string) config('shipping.webhook.global_secret');
        $providedSecret = (string) ($request->header('X-Shipping-Secret') ?? $request->query('secret') ?? '');
        if ($secret !== '' && ! hash_equals($secret, $providedSecret)) {
            CourierApiLog::query()->create([
                'courier_id' => null,
                'courier_account_id' => null,
                'shipment_id' => null,
                'direction' => 'webhook',
                'endpoint' => $request->fullUrl(),
                'http_status' => 401,
                'request_payload' => $payload,
                'response_payload' => null,
                'error_message' => 'Invalid webhook secret',
            ]);

            return response('Unauthorized', 401);
        }

        CourierApiLog::query()->create([
            'courier_id' => null,
            'courier_account_id' => null,
            'shipment_id' => null,
            'direction' => 'webhook',
            'endpoint' => $request->fullUrl(),
            'http_status' => null,
            'request_payload' => $payload,
            'response_payload' => null,
            'error_message' => null,
        ]);

        $tracking = $this->extractTrackingNumber($payload);

        if (is_string($tracking) && $tracking !== '') {
            $shipment = Shipment::query()->where('tracking_number', $tracking)->first();
            if ($shipment !== null) {
                if ($courier === 'postex') {
                    $previous = $shipment->status;
                    $maybe = $this->inferPostExStatus($payload);
                    if ($maybe !== null && $maybe !== $shipment->status) {
                        $shipment->status = $maybe;
                        if ($maybe === ShipmentStatus::Delivered) {
                            $shipment->delivered_at = $shipment->delivered_at ?? now();
                        }
                        if ($maybe === ShipmentStatus::Failed) {
                            $shipment->failed_at = $shipment->failed_at ?? now();
                        }
                        if ($maybe === ShipmentStatus::Canceled) {
                            $shipment->failed_at = $shipment->failed_at ?? now();
                        }

                        $shipment->save();

                        ShipmentEvent::query()->create([
                            'shipment_id' => $shipment->id,
                            'status' => $maybe->value,
                            'description' => 'Status updated from PostEx webhook',
                            'raw_payload' => $payload,
                            'occurred_at' => now(),
                        ]);

                        $this->customerAlerts->notifyStatusTransition($shipment, $previous, $maybe);
                    }

                    if ($this->payloadSuggestsOutForDelivery($payload)) {
                        $this->customerAlerts->notifyOutForDeliveryIfApplicable($shipment);
                    }
                }

                if ($courier === 'trax') {
                    $previous = $shipment->status;
                    $remote = (string) (Arr::get($payload, 'status') ?? Arr::get($payload, 'trackStatus') ?? '');
                    $maybe = TraxStatusMapper::fromText($remote);
                    if ($maybe !== $shipment->status) {
                        $shipment->status = $maybe;
                        if ($maybe === ShipmentStatus::Delivered) {
                            $shipment->delivered_at = $shipment->delivered_at ?? now();
                        }
                        if ($maybe === ShipmentStatus::Failed) {
                            $shipment->failed_at = $shipment->failed_at ?? now();
                        }
                        if ($maybe === ShipmentStatus::Canceled) {
                            $shipment->failed_at = $shipment->failed_at ?? now();
                        }

                        $shipment->save();

                        ShipmentEvent::query()->create([
                            'shipment_id' => $shipment->id,
                            'status' => $maybe->value,
                            'description' => 'Status updated from Trax webhook',
                            'raw_payload' => $payload,
                            'occurred_at' => now(),
                        ]);

                        $this->customerAlerts->notifyStatusTransition($shipment, $previous, $maybe);
                    }

                    if ($this->payloadSuggestsOutForDelivery($payload)) {
                        $this->customerAlerts->notifyOutForDeliveryIfApplicable($shipment);
                    }
                }

                // Always queue a poll as source-of-truth reconciliation.
                SyncShipmentTrackingJob::dispatch($shipment->id);
            }
        }

        return response('OK', 200);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractTrackingNumber(array $payload): ?string
    {
        $flat = [
            $payload['tracking_number'] ?? null,
            $payload['tracking_no'] ?? null,
            $payload['track_no'] ?? null,
            $payload['cn'] ?? null,
            $payload['trackingNumber'] ?? null,
            $payload['tracking_number'] ?? null,
        ];

        foreach ($flat as $v) {
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }

        $candidates = [
            Arr::get($payload, 'dist.trackingNumber'),
            Arr::get($payload, 'dist.tracking_number'),
            Arr::get($payload, 'data.trackingNumber'),
            Arr::get($payload, 'data.tracking_number'),
        ];
        foreach ($candidates as $v) {
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadSuggestsOutForDelivery(array $payload): bool
    {
        $text = strtolower((string) (Arr::get($payload, 'transactionStatus')
            ?? Arr::get($payload, 'dist.transactionStatus')
            ?? Arr::get($payload, 'status')
            ?? Arr::get($payload, 'trackStatus')
            ?? ''));

        return str_contains($text, 'out for delivery')
            || str_contains($text, 'out-for-delivery')
            || str_contains($text, 'out_for_delivery');
    }

    /**
     * Attempts to infer a ShipmentStatus from a PostEx-style webhook payload.
     * Returns null when we cannot confidently infer a status.
     *
     * @param  array<string, mixed>  $payload
     */
    private function inferPostExStatus(array $payload): ?ShipmentStatus
    {
        $code = (string) (Arr::get($payload, 'transactionStatusMessageCode')
            ?? Arr::get($payload, 'dist.transactionStatusMessageCode')
            ?? Arr::get($payload, 'dist.transactionStatusHistory.0.transactionStatusMessageCode')
            ?? '');

        $history = Arr::get($payload, 'dist.transactionStatusHistory');
        if (is_array($history) && count($history) > 0) {
            $last = end($history);
            if (is_array($last)) {
                $code = (string) ($last['transactionStatusMessageCode'] ?? $code);
            }
        }

        $text = strtolower((string) (Arr::get($payload, 'transactionStatus')
            ?? Arr::get($payload, 'dist.transactionStatus')
            ?? Arr::get($payload, 'status')
            ?? Arr::get($payload, 'trackStatus')
            ?? ''));

        return match ($code) {
            '0005' => ShipmentStatus::Delivered,
            '0001' => ShipmentStatus::Booked,
            '0003', '0004', '0018', '0015' => ShipmentStatus::InTransit,
            '0002', '0006', '0007' => ShipmentStatus::Failed,
            '0008', '0013', '0016', '0017' => ShipmentStatus::InTransit,
            default => match (true) {
                str_contains($text, 'deliver') => ShipmentStatus::Delivered,
                str_contains($text, 'transit') || str_contains($text, 'picked') || str_contains($text, 'route') => ShipmentStatus::InTransit,
                str_contains($text, 'return') || str_contains($text, 'fail') => ShipmentStatus::Failed,
                $text !== '' => ShipmentStatus::Booked,
                default => null,
            },
        };
    }
}
