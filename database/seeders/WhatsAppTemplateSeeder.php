<?php

namespace Database\Seeders;

use App\Models\WhatsAppTemplate;
use Illuminate\Database\Seeder;

class WhatsAppTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'key' => 'order_placed',
                'label' => 'Order placed (customer)',
                'audience' => 'customer',
                'category' => 'transactional',
                'body' => "Hi {name}, your order {order} has been placed.\nTotal: PKR {total}.\nWe'll keep you updated.",
                'is_system' => true,
                'description' => 'Sent immediately after non-COD checkout.',
            ],
            [
                'key' => 'order_placed_cod_confirm',
                'label' => 'Order placed — COD confirm buttons',
                'audience' => 'customer',
                'category' => 'transactional',
                'body' => 'Hi {name}, we received your COD order {order} for PKR {total} to {city}. Please confirm so we can dispatch it.',
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
                'body' => "Hi {name}, your order {order} is confirmed. Total: PKR {total}. We'll dispatch it shortly.",
                'is_system' => true,
            ],
            [
                'key' => 'payment_received',
                'label' => 'Payment received',
                'audience' => 'customer',
                'category' => 'transactional',
                'body' => "Hi {name}, payment received for order {order}. Total: PKR {total}. We'll start preparing your shoes now.",
                'is_system' => true,
            ],
            [
                'key' => 'order_packed',
                'label' => 'Order packed',
                'audience' => 'customer',
                'category' => 'transactional',
                'body' => 'Hi {name}, your order {order} is packed and will be handed to the courier soon.',
                'is_system' => true,
            ],
            [
                'key' => 'order_shipped',
                'label' => 'Order shipped',
                'audience' => 'customer',
                'category' => 'transactional',
                'body' => 'Hi {name}, your order {order} has been shipped. Track it from the Track Order page.',
                'is_system' => true,
            ],
            [
                'key' => 'out_for_delivery',
                'label' => 'Out for delivery',
                'audience' => 'customer',
                'category' => 'transactional',
                'body' => 'Hi {name}, your order {order} is out for delivery today. Please keep your phone available.',
                'is_system' => true,
            ],
            [
                'key' => 'order_delivered',
                'label' => 'Order delivered',
                'audience' => 'customer',
                'category' => 'transactional',
                'body' => 'Hi {name}, your order {order} has been delivered. Thank you for shopping with us.',
                'is_system' => true,
            ],
            [
                'key' => 'order_returned',
                'label' => 'Order returned',
                'audience' => 'customer',
                'category' => 'transactional',
                'body' => "Hi {name}, the courier returned your order {order}. Please reach out on WhatsApp if you'd still like it delivered.",
                'is_system' => true,
            ],
            [
                'key' => 'order_canceled',
                'label' => 'Order canceled',
                'audience' => 'customer',
                'category' => 'transactional',
                'body' => 'Hi {name}, your order {order} was canceled. Reply on WhatsApp if this is unexpected.',
                'is_system' => true,
            ],
            [
                'key' => 'admin_new_order',
                'label' => 'Admin — new order alert',
                'audience' => 'admin',
                'category' => 'utility',
                'body' => "New order {order} · PKR {total}\nCustomer: {name} ({phone})\nCity: {city}\nPayment: {payment}\nStatus: {status}",
                'is_system' => true,
            ],
            [
                'key' => 'pickup_notice',
                'label' => 'Courier rider — pickup notice',
                'audience' => 'rider',
                'category' => 'utility',
                'body' => "Salaam, please pick {parcels} parcel(s) from our warehouse today. Total COD: PKR {cod_total}. Tracking #s:\n{tracking_list}",
                'is_system' => true,
                'description' => 'Sent daily to the primary rider per courier company. Placeholders: {parcels}, {cod_total}, {tracking_list}, {courier}.',
            ],
            [
                'key' => 'promotional_default',
                'label' => 'Promotional broadcast',
                'audience' => 'customer',
                'category' => 'marketing',
                'body' => "Hi {name}, we have a fresh drop you'll love. Browse the latest cleats now — reply STOP to opt out.",
                'is_system' => true,
                'description' => 'Default copy used by promotional campaigns. Reply STOP triggers opt-out.',
            ],
        ];

        foreach ($templates as $t) {
            WhatsAppTemplate::query()->updateOrCreate(
                ['key' => $t['key']],
                array_merge([
                    'is_active' => true,
                    'cloud_template_language' => 'en_US',
                    'has_buttons' => false,
                    'button_payloads' => null,
                ], $t),
            );
        }
    }
}
