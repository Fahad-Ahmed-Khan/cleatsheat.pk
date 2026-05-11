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
use App\Models\ShippingSetting;
use Illuminate\Support\Facades\Http;

class PostExCourierAdapter extends AbstractCourierAdapter
{
    public function __construct(
        private readonly CourierApiLogger $logger,
    ) {}

    public function code(): string
    {
        return 'postex';
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
                trackingNumber: 'PEX-'.strtoupper(bin2hex(random_bytes(3))),
                bookingReference: 'PEX-'.$order->id,
                raw: ['sandbox' => true],
            );
        }

        $token = (string) ($account?->credentials['api_token'] ?? '');
        $settings = ShippingSetting::current();
        $recv = $shipment->receiver_snapshot ?? $order->shipping_address_snapshot ?? [];

        $items = $order->items;
        $totalQty = (int) ($items?->sum('quantity') ?? 1);
        if ($totalQty < 1) {
            $totalQty = 1;
        }

        $detail = '';
        if ($items !== null && $items->isNotEmpty()) {
            $parts = [];
            foreach ($items->take(10) as $it) {
                $name = (string) ($it->product_name ?? $it->sku ?? 'Item');
                $qty = (int) ($it->quantity ?? 1);
                $parts[] = $name.' x'.$qty;
            }
            $detail = implode(', ', $parts);
            if ($items->count() > 10) {
                $detail .= ' +'.($items->count() - 10).' more';
            }
        }

        $url = $this->endpointBase('postex').'/services/integration/api/order/v3/create-order';
        $payload = [
            'orderRefNumber' => (string) $order->order_number,
            'invoicePayment' => (float) ($shipment->cod_amount ?? 0),
            'orderDetail' => $detail !== '' ? $detail : null,
            'customerName' => (string) ($recv['full_name'] ?? 'Customer'),
            'customerPhone' => (string) ($recv['phone'] ?? ''),
            'deliveryAddress' => (string) ($recv['line1'] ?? ''),
            'transactionNotes' => (string) ($order->customer_notes ?? ''),
            'cityName' => (string) ($recv['city'] ?? ''),
            'invoiceDivision' => 1,
            'items' => $totalQty,
            'pickupAddressCode' => $settings->postex_pickup_address_code ?: null,
            'storeAddressCode' => $settings->postex_store_address_code ?: null,
            'orderType' => 'Normal',
        ];
        $payload = array_filter($payload, fn ($v) => $v !== null);

        try {
            $response = Http::retry(3, 250)
                ->timeout(45)
                ->acceptJson()
                ->asJson()
                ->withHeaders(['token' => $token])
                ->post($url, $payload);
            $body = $response->json() ?: [];

            $this->logger->log($courier, $account, $shipment, 'request', $url, $response->status(), $payload, $body, null);

            if (! $response->successful()) {
                return new BookingResult(false, raw: $body, errorMessage: (string) ($body['statusMessage'] ?? $body['message'] ?? 'PostEx HTTP error'));
            }

            $statusCode = (string) ($body['statusCode'] ?? '');
            if ($statusCode !== '' && $statusCode !== '200') {
                return new BookingResult(false, raw: $body, errorMessage: (string) ($body['statusMessage'] ?? 'PostEx error'));
            }

            $dist = is_array($body['dist'] ?? null) ? $body['dist'] : [];

            return new BookingResult(
                success: true,
                trackingNumber: (string) ($dist['trackingNumber'] ?? ''),
                bookingReference: (string) ($dist['orderDate'] ?? ''),
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

        $token = (string) ($account?->credentials['api_token'] ?? '');
        $tracking = (string) ($shipment->tracking_number ?? '');
        $url = $this->endpointBase('postex').'/services/integration/api/order/v1/track-order/'.rawurlencode($tracking);

        try {
            $response = Http::retry(3, 250)
                ->timeout(30)
                ->acceptJson()
                ->withHeaders(['token' => $token])
                ->get($url);
            $body = $response->json() ?: [];

            $this->logger->log($courier, $account, $shipment, 'poll', $url, $response->status(), ['trackingNumber' => $tracking], $body, null);

            if (! $response->successful()) {
                return new TrackingResult(status: $shipment->status, raw: $body, publicMessage: (string) ($body['statusMessage'] ?? 'PostEx HTTP error'));
            }

            $dist = is_array($body['dist'] ?? null) ? $body['dist'] : [];
            $history = $dist['transactionStatusHistory'] ?? null;
            $lastCode = null;
            if (is_array($history) && count($history) > 0) {
                $last = end($history);
                if (is_array($last)) {
                    $lastCode = (string) ($last['transactionStatusMessageCode'] ?? '');
                }
            }
            $statusText = strtolower((string) ($dist['transactionStatus'] ?? ''));

            $mapped = $this->mapFromCodeOrText((string) $lastCode, $statusText);

            return new TrackingResult(status: $mapped, raw: $body);
        } catch (\Throwable $e) {
            $this->logger->log($courier, $account, $shipment, 'poll', $url, null, ['trackingNumber' => $tracking], null, $e->getMessage());

            return new TrackingResult(status: $shipment->status, raw: [], publicMessage: $e->getMessage());
        }
    }

    private function mapFromCodeOrText(string $code, string $text): ShipmentStatus
    {
        return match ($code) {
            '0005' => ShipmentStatus::Delivered,
            '0001' => ShipmentStatus::Booked,
            '0003', '0004', '0018', '0015' => ShipmentStatus::InTransit,
            '0002', '0006', '0007' => ShipmentStatus::Failed,
            '0008', '0013', '0016', '0017' => ShipmentStatus::InTransit,
            default => $this->mapFallbackText($text),
        };
    }

    private function mapFallbackText(string $s): ShipmentStatus
    {
        return match (true) {
            str_contains($s, 'deliver') => ShipmentStatus::Delivered,
            str_contains($s, 'transit') || str_contains($s, 'picked') => ShipmentStatus::InTransit,
            str_contains($s, 'return') => ShipmentStatus::Failed,
            str_contains($s, 'fail') => ShipmentStatus::Failed,
            default => ShipmentStatus::Booked,
        };
    }
}
