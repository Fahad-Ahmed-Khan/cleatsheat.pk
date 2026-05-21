<?php

namespace App\Domain\Notifications\WhatsApp;

use App\Enums\OrderStatus;
use App\Models\Order;

final class WhatsAppTemplates
{
    /**
     * @return array{short:string, body:string}
     */
    public static function render(string $templateKey, Order $order): array
    {
        $name = (string) ($order->shipping_address_snapshot['full_name'] ?? 'Customer');
        $orderNo = (string) $order->order_number;
        $total = (string) $order->grand_total;

        return match ($templateKey) {
            'order_placed' => [
                'short' => 'Order placed',
                'body' => "Hi {$name}, your order {$orderNo} has been placed. Total: PKR {$total}. We’ll update you as it moves to dispatch.",
            ],
            'order_placed_cod_confirm' => [
                'short' => 'Confirm COD order',
                'body' => "Hi {$name}, we received your COD order {$orderNo} for PKR {$total}. Please confirm so we can dispatch it.",
            ],
            'order_confirmed' => [
                'short' => 'Order confirmed',
                'body' => "Hi {$name}, your order {$orderNo} is confirmed. Total: PKR {$total}. We’ll dispatch it shortly.",
            ],
            'out_for_delivery' => [
                'short' => 'Out for delivery',
                'body' => "Hi {$name}, your order {$orderNo} is out for delivery today. Please keep your phone available.",
            ],
            'order_returned' => [
                'short' => 'Order returned',
                'body' => "Hi {$name}, the courier returned your order {$orderNo}. Reply on WhatsApp to redeliver.",
            ],
            'payment_received' => [
                'short' => 'Payment received',
                'body' => "Hi {$name}, payment received for order {$orderNo}. Total: PKR {$total}. We’ll start preparing your shoes now.",
            ],
            'order_packed' => [
                'short' => 'Order packed',
                'body' => "Hi {$name}, your order {$orderNo} is packed and will be handed to the courier soon. Total: PKR {$total}.",
            ],
            'order_shipped' => [
                'short' => 'Order shipped',
                'body' => "Hi {$name}, your order {$orderNo} has been shipped. Total: PKR {$total}. You can track it from your account or Track Order page.",
            ],
            'order_delivered' => [
                'short' => 'Order delivered',
                'body' => "Hi {$name}, your order {$orderNo} has been delivered. Total: PKR {$total}. Thank you for shopping with us.",
            ],
            'order_canceled' => [
                'short' => 'Order canceled',
                'body' => "Hi {$name}, your order {$orderNo} was canceled. If this is unexpected, reply on WhatsApp with your order number.",
            ],
            default => [
                'short' => 'Order update',
                'body' => "Hi {$name}, update for order {$orderNo}. Status: ".$order->status->value.'. Total: PKR '.$total.'.',
            ],
        };
    }

    public static function adminNewOrder(Order $order): string
    {
        $name = (string) ($order->shipping_address_snapshot['full_name'] ?? 'Customer');
        $city = (string) ($order->shipping_address_snapshot['city'] ?? '');
        $phone = (string) ($order->shipping_address_snapshot['phone'] ?? '');

        return "New order {$order->order_number} · PKR {$order->grand_total}\n".
            "Customer: {$name} ({$phone})\n".
            "City: {$city}\n".
            "Payment: {$order->payment_gateway} / {$order->payment_status->value}\n".
            "Status: {$order->status->value}";
    }

    public static function keyForStatusTransition(?OrderStatus $from, OrderStatus $to): ?string
    {
        if ($from === $to) {
            return null;
        }

        // "Packed" in UI terms: moving into processing from pending/confirmed (not on initial checkout,
        // which creates the row already in processing — that path never hits `updated`).
        $entersProcessingFromPrep = $to === OrderStatus::Processing
            && $from !== null
            && in_array($from, [OrderStatus::Pending, OrderStatus::Confirmed], true);

        return match (true) {
            $to === OrderStatus::Confirmed && $from !== null => 'order_confirmed',
            $entersProcessingFromPrep => 'order_packed',
            $to === OrderStatus::Shipped => 'order_shipped',
            $to === OrderStatus::Delivered => 'order_delivered',
            $to === OrderStatus::Cancelled => 'order_canceled',
            default => null,
        };
    }
}
