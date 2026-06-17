<?php

namespace App\Domain\Shipping\Couriers;

use App\Domain\Shipping\AbstractCourierAdapter;
use App\Domain\Shipping\CourierApiLogger;
use App\Domain\Shipping\DTOs\BookingResult;
use App\Domain\Shipping\DTOs\TrackingResult;
use App\Domain\Shipping\Trax\TraxApiClient;
use App\Domain\Shipping\Trax\TraxCityResolver;
use App\Domain\Shipping\Trax\TraxStatusMapper;
use App\Domain\Shipping\Trax\TraxTokenResolver;
use App\Enums\ShipmentStatus;
use App\Models\Courier;
use App\Models\CourierAccount;
use App\Models\Shipment;
use App\Models\ShippingSetting;

class TraxCourierAdapter extends AbstractCourierAdapter
{
    public function __construct(
        private readonly CourierApiLogger $logger,
    ) {}

    public function code(): string
    {
        return 'trax';
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
        $order->loadMissing('items');

        if ($this->sandboxMode()) {
            return new BookingResult(
                success: true,
                trackingNumber: 'TRX-'.strtoupper(bin2hex(random_bytes(3))),
                bookingReference: 'TRX-'.$order->id,
                raw: ['sandbox' => true, 'adapter' => 'trax'],
            );
        }

        $token = TraxTokenResolver::forCourierAccount($account);
        if ($token === '') {
            return new BookingResult(false, raw: [], errorMessage: 'Trax (Sonic) API key is missing. Save it under Admin → Shipping → Trax courier account.');
        }
        if ($account === null) {
            return new BookingResult(false, raw: [], errorMessage: 'Trax (Sonic) courier account is missing.');
        }

        $recv = $shipment->receiver_snapshot ?? $order->shipping_address_snapshot ?? [];
        $cityName = (string) ($recv['city'] ?? '');
        $cities = TraxCityResolver::cities($account, $token);
        if (count($cities) === 0) {
            return new BookingResult(
                false,
                raw: ['receiver_city' => $cityName],
                errorMessage: 'Trax booking failed because the Sonic city list could not be fetched (empty result). Verify the Trax API key and environment, then run: php artisan trax:probe cities',
            );
        }
        $cityId = TraxCityResolver::resolveCityId($account, $token, $cityName);
        if ($cityId === null) {
            return new BookingResult(
                false,
                raw: ['receiver_city' => $cityName],
                errorMessage: 'Trax booking failed because receiver city was not matched to a Sonic city ID. Run the Trax probe to verify city list, or adjust the shipping city.',
            );
        }

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

        $base = TraxApiClient::resolvedBaseUrl($account);
        $url = $base.'/api/shipment/book';

        $weight = (float) ($shipment->weight_kg ?? 0);
        if ($weight < 0.01) {
            $weight = 1.0;
        }

        $isCod = (float) ($shipment->cod_amount ?? 0) > 0;
        $codAmount = (int) round((float) ($shipment->cod_amount ?? 0));

        $settings = ShippingSetting::current();
        $pickupAddressId = (int) ($settings->trax_pickup_address_id ?? 0);
        if ($pickupAddressId <= 0) {
            return new BookingResult(
                false,
                raw: [],
                errorMessage: 'Trax booking requires a pickup address ID. Set it under Admin → Shipping → Trax (Sonic) defaults.',
            );
        }

        $shippingModeId = (int) ($settings->trax_shipping_mode_id ?? 1);
        $chargesModeId = (int) ($settings->trax_charges_mode_id ?? 4);
        $itemTypeId = (int) ($settings->trax_item_product_type_id ?? 24);
        $deliveryTypeId = (int) ($settings->trax_delivery_type_id ?? 1);

        $payload = [
            'service_type_id' => 1,
            'pickup_address_id' => $pickupAddressId,
            'information_display' => 0,
            'consignee_city_id' => $cityId,
            'consignee_name' => (string) ($recv['full_name'] ?? 'Customer'),
            'consignee_address' => (string) ($recv['line1'] ?? ''),
            'consignee_phone_number_1' => (string) ($recv['phone'] ?? ''),
            'consignee_phone_number_2' => (string) ($recv['phone2'] ?? ''),
            'consignee_email_address' => (string) ($recv['email'] ?? $order->guest_email ?? ''),
            'order_id' => (string) $order->order_number,
            'item_product_type_id' => $itemTypeId,
            'item_description' => $detail !== '' ? $detail : 'Order '.$order->order_number,
            'item_quantity' => $totalQty,
            'item_insurance' => 0,
            'pickup_date' => now()->format('Y-m-d'),
            'special_instructions' => (string) ($order->customer_notes ?? ''),
            'estimated_weight' => $weight,
            'shipping_mode_id' => $shippingModeId,
            'same_day_timing_id' => null,
            'amount' => $codAmount,
            'payment_mode_id' => $isCod ? 1 : 4,
            'charges_mode_id' => $chargesModeId,
            'delivery_type_id' => $deliveryTypeId,
            'open_shipment' => 0,
            'pieces_quantity' => 1,
        ];
        $payload = array_filter($payload, fn ($v) => $v !== null);

        try {
            $response = TraxApiClient::request($token)->post($url, $payload);
            $body = $response->json() ?: [];

            $this->logger->log($courier, $account, $shipment, 'request', $url, $response->status(), $payload, is_array($body) ? $body : [], null);

            if (! $response->successful()) {
                $msg = is_array($body) ? (string) ($body['message'] ?? $body['error'] ?? 'Trax booking failed') : 'Trax booking failed';

                return new BookingResult(false, raw: is_array($body) ? $body : [], errorMessage: $msg);
            }

            if (! is_array($body)) {
                return new BookingResult(false, raw: [], errorMessage: 'Trax booking failed: unexpected response.');
            }

            if (($body['status'] ?? null) !== 0 && ($body['status'] ?? null) !== '0') {
                return new BookingResult(false, raw: $body, errorMessage: (string) ($body['message'] ?? 'Trax booking failed'));
            }

            $tracking = (string) ($body['tracking number'] ?? $body['tracking_number'] ?? '');
            if (trim($tracking) === '') {
                return new BookingResult(false, raw: $body, errorMessage: 'Trax booking succeeded but no tracking number was returned.');
            }

            return new BookingResult(
                success: true,
                trackingNumber: $tracking,
                bookingReference: isset($body['id']) ? (string) $body['id'] : null,
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

        $token = TraxTokenResolver::forCourierAccount($account);
        if ($token === '' || $account === null) {
            return new TrackingResult(status: $shipment->status, raw: [], publicMessage: 'Trax API key is not configured.');
        }

        $base = TraxApiClient::resolvedBaseUrl($account);
        $url = $base.'/api/shipment/track';
        $payload = [
            'tracking_number' => (string) ($shipment->tracking_number ?? ''),
            'type' => 0,
        ];

        try {
            $response = TraxApiClient::request($token)->get($url, $payload);
            $body = $response->json() ?: [];

            $this->logger->log($courier, $account, $shipment, 'poll', $url, $response->status(), $payload, is_array($body) ? $body : [], null);

            if (! $response->successful() || ! is_array($body)) {
                return new TrackingResult(status: $shipment->status, raw: is_array($body) ? $body : [], publicMessage: 'Trax tracking request failed.');
            }

            $remote = null;
            if (isset($body['current_status'])) {
                $remote = (string) $body['current_status'];
            } elseif (isset($body['details']['tracking_history'][0]['status'])) {
                $remote = (string) $body['details']['tracking_history'][0]['status'];
            }

            return new TrackingResult(status: TraxStatusMapper::fromText($remote), raw: $body);
        } catch (\Throwable $e) {
            $this->logger->log($courier, $account, $shipment, 'poll', $url, null, $payload, null, $e->getMessage());

            return new TrackingResult(status: $shipment->status, raw: [], publicMessage: $e->getMessage());
        }
    }
}

