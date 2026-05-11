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

/** TCS Sentiments — illustrative booking/tracking. */
class TcsCourierAdapter extends AbstractCourierAdapter
{
    public function __construct(
        private readonly CourierApiLogger $logger,
    ) {}

    public function code(): string
    {
        return 'tcs';
    }

    public function supportsLabels(): bool
    {
        return true;
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }

    public function book(Shipment $shipment, Courier $courier, ?CourierAccount $account): BookingResult
    {
        $order = $shipment->order;

        if ($this->sandboxMode()) {
            return new BookingResult(
                success: true,
                trackingNumber: 'TCS-'.time(),
                bookingReference: 'TCS-BK-'.$order->id,
                raw: ['sandbox' => true],
            );
        }

        $url = $this->endpointBase('tcs').'/orders/create';
        $payload = [
            'consignment' => [
                'reference' => $order->order_number,
                'cod' => $shipment->cod_amount,
                'weight' => $shipment->weight_kg,
                'dims_cm' => [$shipment->length_cm, $shipment->width_cm, $shipment->height_cm],
            ],
            'destination' => $shipment->receiver_snapshot ?? $order->shipping_address_snapshot,
            'credentials' => $account?->credentials ?? [],
        ];

        try {
            $response = Http::retry(3, 250)->timeout(45)->acceptJson()->asJson()->post($url, $payload);
            $body = $response->json() ?: [];

            $this->logger->log($courier, $account, $shipment, 'request', $url, $response->status(), $payload, $body, null);

            if (! $response->successful()) {
                return new BookingResult(false, raw: $body, errorMessage: $body['msg'] ?? 'TCS booking failed');
            }

            return new BookingResult(
                success: true,
                trackingNumber: (string) ($body['cn_number'] ?? $body['tracking'] ?? ''),
                bookingReference: (string) ($body['booking_ref'] ?? ''),
                labelUrl: $body['label'] ?? null,
                shippingCharges: isset($body['freight']) ? (string) $body['freight'] : null,
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

        $url = $this->endpointBase('tcs').'/track';
        $payload = ['cn' => $shipment->tracking_number];

        try {
            $response = Http::retry(3, 250)->timeout(30)->acceptJson()->asJson()->post($url, $payload);
            $body = $response->json() ?: [];

            $this->logger->log($courier, $account, $shipment, 'poll', $url, $response->status(), $payload, $body, null);

            $mapped = $this->map(strtolower((string) ($body['current_status'] ?? $body['status'] ?? '')));

            return new TrackingResult(status: $mapped, raw: $body);
        } catch (\Throwable $e) {
            $this->logger->log($courier, $account, $shipment, 'poll', $url, null, $payload, null, $e->getMessage());

            return new TrackingResult(status: $shipment->status, raw: [], publicMessage: $e->getMessage());
        }
    }

    private function map(string $s): ShipmentStatus
    {
        return match (true) {
            str_contains($s, 'deliver') => ShipmentStatus::Delivered,
            str_contains($s, 'transit') || str_contains($s, 'route') => ShipmentStatus::InTransit,
            str_contains($s, 'cancel') => ShipmentStatus::Canceled,
            str_contains($s, 'fail') || str_contains($s, 'undeliver') => ShipmentStatus::Failed,
            default => ShipmentStatus::Booked,
        };
    }
}
