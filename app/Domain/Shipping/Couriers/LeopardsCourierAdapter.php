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

/**
 * Leopards Courier — adjust payload/URLs to match your merchant API contract.
 */
class LeopardsCourierAdapter extends AbstractCourierAdapter
{
    public function __construct(
        private readonly CourierApiLogger $logger,
    ) {}

    public function code(): string
    {
        return 'leopards';
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
                trackingNumber: 'LEO-'.strtoupper(substr($order->order_number, -10)),
                bookingReference: 'LEO-BK-'.$order->id,
                labelUrl: null,
                raw: ['sandbox' => true, 'adapter' => 'leopards'],
            );
        }

        $url = $this->endpointBase('leopards').'/book';
        $payload = $this->buildBookPayload($shipment, $courier, $account);

        try {
            $response = Http::retry(3, 250)->timeout(45)->acceptJson()->asJson()->post($url, $payload);
            $body = $response->json() ?: [];

            $this->logger->log($courier, $account, $shipment, 'request', $url, $response->status(), $payload, $body, null);

            if (! $response->successful()) {
                return new BookingResult(false, raw: $body, errorMessage: $body['message'] ?? 'Leopards booking failed');
            }

            return new BookingResult(
                success: true,
                trackingNumber: (string) ($body['tracking_number'] ?? $body['track_no'] ?? ''),
                bookingReference: (string) ($body['reference'] ?? $body['booking_id'] ?? ''),
                labelUrl: $body['label_url'] ?? null,
                invoiceUrl: $body['invoice_url'] ?? null,
                shippingCharges: isset($body['shipping_charges']) ? (string) $body['shipping_charges'] : null,
                raw: $body,
            );
        } catch (\Throwable $e) {
            $this->logger->log($courier, $account, $shipment, 'request', $url, null, $payload, null, $e->getMessage());

            return new BookingResult(false, raw: [], errorMessage: $e->getMessage());
        }
    }

    public function track(Shipment $shipment, Courier $courier, ?CourierAccount $account): TrackingResult
    {
        if ($this->sandboxMode()) {
            return new TrackingResult(status: ShipmentStatus::InTransit, raw: ['sandbox' => true]);
        }

        $tn = $shipment->tracking_number;
        $url = $this->endpointBase('leopards').'/track/'.$tn;

        try {
            $response = Http::retry(3, 250)->timeout(30)->acceptJson()->get($url, [
                'token' => $account?->credentials['api_token'] ?? '',
            ]);
            $body = $response->json() ?: [];

            $this->logger->log($courier, $account, $shipment, 'poll', $url, $response->status(), ['tracking' => $tn], $body, null);

            $mapped = $this->mapTrackStatus((string) ($body['status'] ?? ''));

            return new TrackingResult(status: $mapped, raw: $body);
        } catch (\Throwable $e) {
            $this->logger->log($courier, $account, $shipment, 'poll', $url, null, ['tracking' => $tn], null, $e->getMessage());

            return new TrackingResult(status: $shipment->status, raw: [], publicMessage: $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBookPayload(Shipment $shipment, Courier $courier, ?CourierAccount $account): array
    {
        $order = $shipment->order;
        $recv = $shipment->receiver_snapshot ?? $order->shipping_address_snapshot;

        return [
            'merchant_order_id' => $order->order_number,
            'cod_amount' => $shipment->cod_amount,
            'weight_kg' => $shipment->weight_kg,
            'dimensions' => [
                'l' => $shipment->length_cm,
                'w' => $shipment->width_cm,
                'h' => $shipment->height_cm,
            ],
            'shipper' => $shipment->sender_snapshot,
            'consignee' => $recv,
            'credentials' => $account?->credentials ?? [],
        ];
    }

    private function mapTrackStatus(string $remote): ShipmentStatus
    {
        $s = strtolower($remote);

        return match (true) {
            str_contains($s, 'deliver') => ShipmentStatus::Delivered,
            str_contains($s, 'transit') || str_contains($s, 'dispatch') => ShipmentStatus::InTransit,
            str_contains($s, 'fail') || str_contains($s, 'rto') => ShipmentStatus::Failed,
            str_contains($s, 'cancel') => ShipmentStatus::Canceled,
            default => ShipmentStatus::Booked,
        };
    }
}
