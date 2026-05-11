<?php

namespace App\Domain\Shipping\Couriers;

use App\Domain\Shipping\AbstractCourierAdapter;
use App\Domain\Shipping\CourierApiLogger;
use App\Domain\Shipping\DTOs\BookingResult;
use App\Domain\Shipping\DTOs\TrackingResult;
use App\Enums\ShipmentStatus;
use App\Models\Courier;
use App\Models\CourierAccount;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Http;

/** Run Courier (portal.runcourier.com) — Create Order + Current Status. */
class RunCourierAdapter extends AbstractCourierAdapter
{
    public function __construct(
        private readonly CourierApiLogger $logger,
    ) {}

    public function code(): string
    {
        return 'runcourier';
    }

    public function supportsLabels(): bool
    {
        return false;
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }

    public function book(Shipment $shipment, Courier $courier, ?CourierAccount $account): BookingResult
    {
        $order = $shipment->order;
        $order->loadMissing('items');

        if ($this->sandboxMode()) {
            return new BookingResult(
                success: true,
                trackingNumber: 'RC-'.strtoupper(substr($order->order_number, -10)),
                bookingReference: 'RC-BK-'.$order->id,
                invoiceUrl: null,
                raw: ['sandbox' => true, 'adapter' => 'runcourier'],
            );
        }

        $creds = $account?->credentials ?? [];
        $authKey = (string) ($creds['api_token'] ?? '');
        $clientCode = (string) ($creds['client_code'] ?? '');
        $profileId = (string) ($creds['profile_id'] ?? '');
        $apiVendor = (string) ($creds['api_vendor'] ?? 'auto');

        if ($authKey === '' || $clientCode === '' || $profileId === '') {
            return new BookingResult(false, errorMessage: 'Run Courier requires api_token (auth key), client_code, and profile_id in courier account credentials.');
        }

        $serviceProduct = $this->serviceAndProduct($account);
        $recv = $shipment->receiver_snapshot ?? $order->shipping_address_snapshot ?? [];
        $sender = is_array($shipment->sender_snapshot) ? $shipment->sender_snapshot : [];

        $origin = (string) ($sender['city'] ?? '');
        $destination = (string) ($recv['city'] ?? '');
        $weight = max(0.001, (float) $shipment->weight_kg);
        if ($weight < 0.01) {
            $weight = 1.0;
        }

        $payload = [
            'client_code' => $clientCode,
            'auth_key' => $authKey,
            'service_type' => $serviceProduct['service_type'],
            'product' => $serviceProduct['product'],
            'profile_id' => $profileId,
            'origin' => $origin,
            'receiver_phone' => $this->normalizePhone((string) ($recv['phone'] ?? '')),
            'destination' => $destination,
            'receiver_name' => (string) ($recv['full_name'] ?? ''),
            'receiver_email' => (string) ($recv['email'] ?? $order->guest_email ?? ''),
            'receiver_address' => $this->receiverAddressLine($recv),
            'pieces' => 1,
            'tracking_no' => '',
            'weight' => $weight,
            'order_date' => now()->format('Y-m-d H:i:s'),
            'collection_amount' => $shipment->cod_amount !== null
                ? (string) $shipment->cod_amount
                : '0',
            'product_description' => $this->productDescription($order),
            'special_instruction' => '',
            'order_id' => $order->order_number,
            'api_vendor' => $apiVendor !== '' ? $apiVendor : 'auto',
        ];

        $url = $this->endpointBase('runcourier').'/API/CreateOrder.php';

        try {
            $response = Http::retry(3, 250)->timeout(45)->acceptJson()->asJson()->post($url, $payload);
            $body = $response->json();
            if (! is_array($body)) {
                $body = [];
            }

            $logPayload = $this->redactPayloadForLog($payload);
            $this->logger->log($courier, $account, $shipment, 'request', $url, $response->status(), $logPayload, $body, null);

            if (! $response->successful()) {
                return new BookingResult(false, raw: $body, errorMessage: (string) ($body['message'] ?? $body['error'] ?? 'Run Courier booking failed'));
            }

            $tracking = $body['tracking_no'] ?? null;
            if ($tracking === null || $tracking === '') {
                return new BookingResult(false, raw: $body, errorMessage: (string) ($body['message'] ?? 'Run Courier did not return tracking_no'));
            }

            return new BookingResult(
                success: true,
                trackingNumber: (string) $tracking,
                bookingReference: isset($body['id']) ? (string) $body['id'] : null,
                invoiceUrl: isset($body['invoice_link']) ? (string) $body['invoice_link'] : null,
                raw: $body,
            );
        } catch (\Throwable $e) {
            $this->logger->log($courier, $account, $shipment, 'request', $url, null, $this->redactPayloadForLog($payload), null, $e->getMessage());

            return new BookingResult(false, errorMessage: $e->getMessage());
        }
    }

    public function track(Shipment $shipment, Courier $courier, ?CourierAccount $account): TrackingResult
    {
        if ($this->sandboxMode()) {
            return new TrackingResult(status: ShipmentStatus::InTransit, raw: ['sandbox' => true]);
        }

        $creds = $account?->credentials ?? [];
        $authKey = (string) ($creds['api_token'] ?? '');
        $payload = [
            'tracking_no' => (string) $shipment->tracking_number,
        ];
        if ($authKey !== '') {
            $payload['auth_key'] = $authKey;
        }

        $url = $this->endpointBase('runcourier').'/API/CurrentStatus.php';

        try {
            $response = Http::retry(3, 250)->timeout(30)->acceptJson()->asJson()->post($url, $payload);
            $body = $response->json();

            $logPayload = $authKey !== '' ? array_merge($payload, ['auth_key' => '***']) : $payload;
            $this->logger->log($courier, $account, $shipment, 'poll', $url, $response->status(), $logPayload, is_array($body) ? $body : [], null);

            if (! $response->successful()) {
                return new TrackingResult(status: $shipment->status, raw: is_array($body) ? $body : [], publicMessage: 'Run Courier status request failed');
            }

            $row = null;
            if (is_array($body) && $body !== []) {
                if (isset($body['status'])) {
                    $row = $body;
                } else {
                    $first = $body[0] ?? null;
                    $row = is_array($first) ? $first : null;
                }
            }

            $statusText = is_array($row) ? (string) ($row['status'] ?? '') : '';

            return new TrackingResult(
                status: $this->mapStatus(strtolower($statusText)),
                raw: is_array($body) ? $body : [],
            );
        } catch (\Throwable $e) {
            $this->logger->log($courier, $account, $shipment, 'poll', $url, null, $payload, null, $e->getMessage());

            return new TrackingResult(status: $shipment->status, raw: [], publicMessage: $e->getMessage());
        }
    }

    /**
     * @return array{service_type: string, product: string}
     */
    private function serviceAndProduct(?CourierAccount $account): array
    {
        $code = trim((string) ($account?->service_code ?? ''));
        if ($code === '') {
            return ['service_type' => 'Overnight', 'product' => 'Overnight'];
        }

        return ['service_type' => $code, 'product' => $code];
    }

    /**
     * @param  array<string, mixed>  $recv
     */
    private function receiverAddressLine(array $recv): string
    {
        $parts = array_filter([
            $recv['line1'] ?? null,
            $recv['area'] ?? null,
            $recv['postal_code'] ?? null,
            $recv['city'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        return implode(', ', $parts);
    }

    private function normalizePhone(string $phone): string
    {
        $t = preg_replace('/\s+/', '', $phone) ?? $phone;

        return $t !== '' ? $t : '0000000000';
    }

    private function productDescription(Order $order): string
    {
        $items = $order->items;
        if ($items->isEmpty()) {
            return 'Order '.$order->order_number;
        }

        $first = $items->first();
        $desc = ($first->product_name ?? 'Item').' x'.$first->quantity;
        if ($items->count() > 1) {
            $desc .= ' (+'.($items->count() - 1).' more)';
        }

        return mb_substr($desc, 0, 500);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redactPayloadForLog(array $payload): array
    {
        $out = $payload;
        if (isset($out['auth_key'])) {
            $out['auth_key'] = '***';
        }

        return $out;
    }

    private function mapStatus(string $s): ShipmentStatus
    {
        return match (true) {
            str_contains($s, 'not delivered')
                || str_contains($s, 'undelivered')
                || str_contains($s, 'unsuccessful') => ShipmentStatus::Failed,
            str_contains($s, 'delivered') => ShipmentStatus::Delivered,
            str_contains($s, 'cancel') => ShipmentStatus::Canceled,
            str_contains($s, 'lost')
                || str_contains($s, 'refused')
                || str_contains($s, 'claim')
                || str_contains($s, 'delivery unsuccessful') => ShipmentStatus::Failed,
            str_contains($s, 'return')
                || str_contains($s, 'rto')
                || str_contains($s, 'origin')
                || str_contains($s, 'shipper') => ShipmentStatus::Failed,
            str_contains($s, 'transit')
                || str_contains($s, 'delivery')
                || str_contains($s, 'picked')
                || str_contains($s, 'pick up')
                || str_contains($s, 'received')
                || str_contains($s, 'office')
                || str_contains($s, 'parcel')
                || str_contains($s, 'attempt')
                || str_contains($s, 'hold')
                || str_contains($s, 'out for') => ShipmentStatus::InTransit,
            default => ShipmentStatus::Booked,
        };
    }
}
