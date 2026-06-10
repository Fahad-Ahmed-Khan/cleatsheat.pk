<?php

namespace App\Domain\Notifications\WhatsApp;

use App\Models\Order;
use App\Models\WhatsAppTemplate;

/**
 * Resolves a WhatsApp template by key. Prefers admin-editable DB rows from
 * `whatsapp_templates`; falls back to the code defaults in WhatsAppTemplates
 * when the row is missing or inactive. This lets admins edit copy without
 * shipping a release while keeping a safe default that is always present.
 */
class TemplateRepository
{
    /**
     * @return array{short:string, body:string, header_text:?string, footer_text:?string, url_buttons:array<int,array{text:string,url:string}>, has_buttons:bool, button_payloads:array<int,array<string,string>>, cloud_template_name:?string, cloud_template_language:string, meta_parameter_order:?array, key:string}
     */
    public function resolve(string $key, ?Order $order = null): array
    {
        $row = WhatsAppTemplate::findActiveByKey($key);

        if ($row !== null) {
            $body = $order !== null
                ? $this->renderPlaceholders($row->body, $order)
                : $row->body;

            $short = $this->shortFromBody($body);

            $cloudName = (string) ($row->cloud_template_name ?? '');
            if ($cloudName === '') {
                $cloudName = (string) (config("whatsapp.cloud.templates.{$key}.name") ?? '');
            }
            $cloudLang = $row->cloud_template_language ?: (string) (config("whatsapp.cloud.templates.{$key}.language") ?? 'en_US');
            $paramOrder = is_array($row->meta_parameter_order) && $row->meta_parameter_order !== []
                ? array_values(array_map(static fn (mixed $v): string => (string) $v, $row->meta_parameter_order))
                : null;

            $headerText = trim((string) ($row->header_text ?? ''));
            if ($headerText !== '' && $order !== null) {
                $headerText = $this->renderPlaceholders($headerText, $order);
            }

            return [
                'short' => $short,
                'body' => $body,
                'header_text' => $headerText !== '' ? $headerText : null,
                'footer_text' => trim((string) ($row->footer_text ?? '')) !== '' ? trim((string) $row->footer_text) : null,
                'url_buttons' => $this->normalizeUrlButtons($row->url_buttons),
                'has_buttons' => (bool) $row->has_buttons,
                'button_payloads' => $this->normalizeButtonPayloads($row->button_payloads, $order),
                'cloud_template_name' => $cloudName !== '' ? $cloudName : null,
                'cloud_template_language' => $cloudLang !== '' ? $cloudLang : 'en_US',
                'meta_parameter_order' => $paramOrder,
                'key' => $key,
            ];
        }

        if ($order !== null) {
            $rendered = WhatsAppTemplates::render($key, $order);
        } else {
            $rendered = ['short' => 'Update', 'body' => ''];
        }

        $cloudName = (string) (config("whatsapp.cloud.templates.{$key}.name") ?? '');
        $cloudLang = (string) (config("whatsapp.cloud.templates.{$key}.language") ?? 'en_US');

        return [
            'short' => $rendered['short'],
            'body' => $rendered['body'],
            'header_text' => null,
            'footer_text' => null,
            'url_buttons' => [],
            'has_buttons' => false,
            'button_payloads' => [],
            'cloud_template_name' => $cloudName !== '' ? $cloudName : null,
            'cloud_template_language' => $cloudLang !== '' ? $cloudLang : 'en_US',
            'meta_parameter_order' => null,
            'key' => $key,
        ];
    }

    /**
     * Replace inline placeholders in admin-editable templates.
     * Supported tokens:
     *   {name}            - customer full name
     *   {order}           - order number
     *   {total}           - grand total (string)
     *   {status}          - order status value
     *   {payment}         - payment gateway label
     *   {phone}           - customer phone
     *   {city}            - shipping city
     *   {courier}         - courier name of the latest shipment
     *   {tracking_number} - tracking number of the latest shipment
     *   {tracking_url}    - public track-order deep link for this order
     *   {review_url}      - public review page URL
     *   {order_url}       - account order details URL (requires login)
     */
    public function renderPlaceholders(string $body, Order $order): string
    {
        $snap = is_array($order->shipping_address_snapshot) ? $order->shipping_address_snapshot : [];
        $shipment = $this->latestShipment($order);

        $replacements = [
            '{name}' => (string) ($snap['full_name'] ?? 'Customer'),
            '{order}' => (string) $order->order_number,
            '{total}' => (string) $order->grand_total,
            '{status}' => (string) $order->status->value,
            '{payment}' => (string) ($order->payment_gateway ?? ''),
            '{phone}' => (string) ($snap['phone'] ?? ''),
            '{city}' => (string) ($snap['city'] ?? ''),
            '{courier}' => (string) ($shipment?->courier?->name ?? 'our courier partner'),
            '{tracking_number}' => (string) ($shipment?->tracking_number ?? ''),
            '{tracking_url}' => self::trackingUrl($order),
            '{review_url}' => route('store.review'),
            '{order_url}' => route('store.account.orders.show', ['order_number' => $order->order_number]),
        ];

        return strtr($body, $replacements);
    }

    public static function trackingUrl(Order $order): string
    {
        return route('store.order-tracking').'?order='.rawurlencode((string) $order->order_number);
    }

    private function latestShipment(Order $order): ?\App\Models\Shipment
    {
        if ($order->relationLoaded('shipments')) {
            return $order->shipments->sortByDesc('id')->first();
        }

        if (! $order->exists) {
            return null;
        }

        return $order->shipments()->with('courier')->latest('id')->first();
    }

    /**
     * @param  mixed  $raw
     * @return array<int, array{text:string, url:string}>
     */
    private function normalizeUrlButtons($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $btn) {
            if (! is_array($btn)) {
                continue;
            }
            $text = trim((string) ($btn['text'] ?? ''));
            $url = trim((string) ($btn['url'] ?? ''));
            if ($text === '' || $url === '') {
                continue;
            }

            $out[] = ['text' => mb_substr($text, 0, 25), 'url' => $url];
        }

        return array_slice($out, 0, 2);
    }

    /**
     * @param  mixed  $raw
     * @return array<int, array<string,string>>
     */
    private function normalizeButtonPayloads($raw, ?Order $order): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $btn) {
            if (! is_array($btn)) {
                continue;
            }
            $id = (string) ($btn['id'] ?? '');
            $title = (string) ($btn['title'] ?? '');
            if ($id === '' || $title === '') {
                continue;
            }

            if ($order !== null) {
                $id = strtr($id, ['{order_id}' => (string) $order->id, '{order_number}' => (string) $order->order_number]);
            }

            $out[] = ['id' => $id, 'title' => mb_substr($title, 0, 20)];
        }

        return array_slice($out, 0, 3);
    }

    private function shortFromBody(string $body): string
    {
        $first = strtok($body, "\n");
        $first = is_string($first) ? trim($first) : '';
        if ($first === '') {
            return 'Update';
        }

        return mb_substr($first, 0, 60);
    }
}
