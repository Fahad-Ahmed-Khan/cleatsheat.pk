<?php

namespace Database\Seeders;

use App\Domain\Notifications\WhatsApp\WhatsAppTemplateSyncService;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Seeder;

class WhatsAppTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $brand = (string) config('whatsapp.brand_name', 'CleatSheat.pk');
        $footer = mb_substr($brand.' - thank you for shopping with us', 0, 60);

        $trackingUrl = route('store.order-tracking').'?order={order_number}';
        $reviewUrl = route('store.review');

        $trackButton = ['text' => 'Track Order', 'url' => $trackingUrl];
        $reviewButton = ['text' => 'Leave a Review', 'url' => $reviewUrl];

        $templates = [
            [
                'key' => 'order_placed',
                'label' => 'Order placed (customer)',
                'audience' => 'customer',
                'category' => 'transactional',
                'header_text' => 'Order Received',
                'body' => "Hi {name},\n\nThank you for shopping at {$brand}! We have received your order {order}.\n\nOrder total: PKR {total}\nPayment method: {payment}\n\nWe will message you as soon as it is confirmed and on its way. Tap the button below to track your order anytime.",
                'footer_text' => $footer,
                'url_buttons' => [$trackButton],
                'is_system' => true,
                'description' => 'Sent immediately after non-COD checkout.',
            ],
            [
                'key' => 'order_placed_cod_confirm',
                'label' => 'Order placed — COD confirm buttons',
                'audience' => 'customer',
                'category' => 'transactional',
                'header_text' => 'Confirm Your Order',
                'body' => "Hi {name},\n\nWe received your Cash on Delivery order {order} from {$brand}.\n\nOrder total: PKR {total}\nDelivery city: {city}\nContact number: {phone}\n\nPlease tap Confirm if these details are correct so we can dispatch your order, or Cancel if you did not place it.\n\nNeed to change your shoe size or address? Just reply to this message and our team will update it for you.",
                'footer_text' => $footer,
                'has_buttons' => true,
                'button_payloads' => [
                    ['id' => 'order:{order_id}:confirm', 'title' => 'Confirm'],
                    ['id' => 'order:{order_id}:cancel', 'title' => 'Cancel'],
                ],
                'is_system' => true,
                'description' => 'Sent for COD orders. Customer Confirm/Cancel reply transitions the order status.',
            ],
            [
                'key' => 'order_confirmed',
                'label' => 'Order confirmed',
                'audience' => 'customer',
                'category' => 'transactional',
                'header_text' => 'Order Confirmed',
                'body' => "Hi {name},\n\nGreat news! Your {$brand} order {order} has been confirmed.\n\nOrder total: PKR {total}\n\nWe are now preparing your order for dispatch and will message you again once it ships. Tap the button below to follow every step.",
                'footer_text' => $footer,
                'url_buttons' => [$trackButton],
                'is_system' => true,
            ],
            [
                'key' => 'payment_received',
                'label' => 'Payment received',
                'audience' => 'customer',
                'category' => 'transactional',
                'header_text' => 'Payment Received',
                'body' => "Hi {name},\n\nWe have received your payment of PKR {total} for order {order} at {$brand}. Thank you!\n\nYour order is now being prepared and we will keep you updated at every step.",
                'footer_text' => $footer,
                'url_buttons' => [$trackButton],
                'is_system' => true,
            ],
            [
                'key' => 'order_packed',
                'label' => 'Order packed',
                'audience' => 'customer',
                'category' => 'transactional',
                'header_text' => 'Order Packed',
                'body' => "Hi {name},\n\nYour {$brand} order {order} has been packed and will be handed over to our courier partner shortly.\n\nWe will share the tracking details with you as soon as it ships.",
                'footer_text' => $footer,
                'url_buttons' => [$trackButton],
                'is_system' => true,
            ],
            [
                'key' => 'order_shipped',
                'label' => 'Order shipped',
                'audience' => 'customer',
                'category' => 'transactional',
                'header_text' => 'Order Shipped',
                'body' => "Hi {name},\n\nYour {$brand} order {order} is on its way!\n\nCourier: {courier}\nTracking number: {tracking_number}\n\nTap the button below to see live tracking updates and your full delivery timeline.",
                'footer_text' => $footer,
                'url_buttons' => [$trackButton],
                'is_system' => true,
            ],
            [
                'key' => 'out_for_delivery',
                'label' => 'Out for delivery',
                'audience' => 'customer',
                'category' => 'transactional',
                'header_text' => 'Out for Delivery',
                'body' => "Hi {name},\n\nYour {$brand} order {order} is out for delivery today. Please keep your phone reachable - the rider may call you before arriving.\n\nIf you chose Cash on Delivery, kindly keep PKR {total} ready.",
                'footer_text' => $footer,
                'url_buttons' => [$trackButton],
                'is_system' => true,
            ],
            [
                'key' => 'order_delivered',
                'label' => 'Order delivered',
                'audience' => 'customer',
                'category' => 'transactional',
                'header_text' => 'Order Delivered',
                'body' => "Hi {name},\n\nYour {$brand} order {order} has been delivered. We hope you love your new gear!\n\nIf anything is not right, just reply to this message and we will sort it out right away.\n\nLoved your experience? We would really appreciate a quick review - it helps other players shop with confidence.",
                'footer_text' => $footer,
                'url_buttons' => [$trackButton, $reviewButton],
                'is_system' => true,
            ],
            [
                'key' => 'order_returned',
                'label' => 'Order returned',
                'audience' => 'customer',
                'category' => 'transactional',
                'header_text' => 'Delivery Unsuccessful',
                'body' => "Hi {name},\n\nThe courier could not deliver your {$brand} order {order} and it is being returned to us.\n\nIf you would still like to receive it, simply reply to this message and we will arrange redelivery for you.",
                'footer_text' => $footer,
                'url_buttons' => [$trackButton],
                'is_system' => true,
            ],
            [
                'key' => 'order_canceled',
                'label' => 'Order canceled',
                'audience' => 'customer',
                'category' => 'transactional',
                'header_text' => 'Order Cancelled',
                'body' => "Hi {name},\n\nYour {$brand} order {order} has been cancelled.\n\nIf you did not request this, or you would like to reorder, reply to this message and our team will help you right away.",
                'footer_text' => $footer,
                'is_system' => true,
            ],
            [
                'key' => 'admin_new_order',
                'label' => 'Admin — new order alert',
                'audience' => 'admin',
                'category' => 'utility',
                'header_text' => 'New Order Alert',
                'body' => "New {$brand} order {order} - PKR {total}\n\nCustomer: {name} ({phone})\nCity: {city}\nPayment: {payment}\nStatus: {status}\n\nPlease review and process it in the admin panel.",
                'is_system' => true,
            ],
            [
                'key' => 'pickup_notice',
                'label' => 'Courier rider — pickup notice',
                'audience' => 'rider',
                'category' => 'utility',
                'header_text' => 'Pickup Request',
                'body' => "Salaam, please arrange pickup of {parcels} parcel(s) from our {$brand} warehouse today.\n\nCOD total: PKR {cod_total}\nTracking numbers: {tracking_list}\n\nThank you.",
                'is_system' => true,
                'description' => 'Sent daily to the primary rider per courier company. Placeholders: {parcels}, {cod_total}, {tracking_list}, {courier}.',
            ],
            [
                'key' => 'promotional_default',
                'label' => 'Promotional broadcast',
                'audience' => 'customer',
                'category' => 'marketing',
                'body' => "Hi {name}, fresh arrivals just dropped at {$brand}! Browse the latest cleats and gear before your size runs out. Reply STOP to opt out.",
                'footer_text' => mb_substr($brand.' - reply STOP to opt out', 0, 60),
                'is_system' => true,
                'description' => 'Default copy used by promotional campaigns. Reply STOP triggers opt-out.',
            ],
        ];

        foreach ($templates as $t) {
            $hasButtons = (bool) ($t['has_buttons'] ?? false);

            WhatsAppTemplate::query()->updateOrCreate(
                ['key' => $t['key']],
                array_merge([
                    'is_active' => true,
                    'cloud_template_language' => 'en_US',
                    'cloud_template_name' => $hasButtons
                        ? null
                        : WhatsAppTemplateSyncService::defaultMetaNameForKey((string) $t['key']),
                    'has_buttons' => false,
                    'button_payloads' => null,
                    'header_text' => null,
                    'footer_text' => null,
                    'url_buttons' => null,
                ], $t),
            );
        }
    }
}
