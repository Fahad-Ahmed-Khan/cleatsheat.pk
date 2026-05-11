<?php

namespace App\Domain\Shipping\Couriers;

use App\Domain\Shipping\AbstractCourierAdapter;
use App\Domain\Shipping\CourierApiLogger;
use App\Domain\Shipping\DTOs\BookingResult;
use App\Domain\Shipping\DTOs\TrackingResult;
use App\Enums\ShipmentStatus;
use App\Models\Courier;
use App\Models\CourierAccount;
use App\Models\Shipment;
use Illuminate\Support\Facades\Http;

/** M&P / Muller & Phipps logistics-style adapter — payload is illustrative. */
class MpCourierAdapter extends AbstractCourierAdapter
{
    public function __construct(
        private readonly CourierApiLogger $logger,
    ) {}

    public function code(): string
    {
        return 'mp';
    }

    public function supportsLabels(): bool
    {
        return true;
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }

    public function book(Shipment $shipment, Courier $courier, ?CourierAccount $account): BookingResult
    {
        $order = $shipment->order;

        if ($this->sandboxMode()) {
            return new BookingResult(
                success: true,
                trackingNumber: 'MP-'.$order->id.'-'.substr(md5($order->order_number), 0, 6),
                bookingReference: 'MP-BK-'.$order->id,
                raw: ['sandbox' => true],
            );
        }

        $url = $this->endpointBase('mp').'/shipments';
        $payload = [
            'reference' => $order->order_number,
            'cod' => $shipment->cod_amount,
            'parcel' => [
                'weight_kg' => $shipment->weight_kg,
                'length_cm' => $shipment->length_cm,
                'width_cm' => $shipment->width_cm,
                'height_cm' => $shipment->height_cm,
            ],
            'to' => $shipment->receiver_snapshot ?? $order->shipping_address_snapshot,
            'auth' => $account?->credentials ?? [],
        ];

        try {
            $response = Http::retry(3, 250)->timeout(45)->acceptJson()->asJson()->post($url, $payload);
            $body = $response->json() ?: [];

            $this->logger->log($courier, $account, $shipment, 'request', $url, $response->status(), $payload, $body, null);

            if (! $response->successful()) {
                return new BookingResult(false, raw: $body, errorMessage: $body['error'] ?? 'M&P booking failed');
            }

            return new BookingResult(
                success: true,
                trackingNumber: (string) ($body['awb'] ?? $body['tracking'] ?? ''),
                bookingReference: (string) ($body['booking_id'] ?? ''),
                labelUrl: $body['label_url'] ?? null,
                raw: $body,
            );
        } catch (\Throwable $e) {
            $this->logger->log($courier, $account, $shipment, 'request', $url, null, $payload, null, $e->getMessage());

            return new BookingResult(false, errorMessage: $e->getMessage());
        }
    }

    public function track(Shipment $shipment, Courier $courier, ?CourierAccount $account): TrackingResult
    {
        if ($this->sandboxMode()) {
            return new TrackingResult(status: ShipmentStatus::InTransit, raw: ['sandbox' => true]);
        }

        $url = $this->endpointBase('mp').'/track/'.$shipment->tracking_number;

        try {
            $response = Http::retry(3, 250)->timeout(30)->acceptJson()->get($url);
            $body = $response->json() ?: [];

            $this->logger->log($courier, $account, $shipment, 'poll', $url, $response->status(), [], $body, null);

            $mapped = $this->map(strtolower((string) ($body['status'] ?? '')));

            return new TrackingResult(status: $mapped, raw: $body);
        } catch (\Throwable $e) {
            $this->logger->log($courier, $account, $shipment, 'poll', $url, null, [], null, $e->getMessage());

            return new TrackingResult(status: $shipment->status, raw: [], publicMessage: $e->getMessage());
        }
    }

    private function map(string $s): ShipmentStatus
    {
        return match (true) {
            str_contains($s, 'deliver') => ShipmentStatus::Delivered,
            str_contains($s, 'transit') => ShipmentStatus::InTransit,
            str_contains($s, 'fail') => ShipmentStatus::Failed,
            default => ShipmentStatus::Booked,
        };
    }
}
