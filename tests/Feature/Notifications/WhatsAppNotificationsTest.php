<?php

namespace Tests\Feature\Notifications;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\WhatsAppSetting;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stub_send_logs_sent_when_cloud_disabled(): void
    {
        Config::set('whatsapp.cloud.enabled', false);
        Config::set('whatsapp.bridge.api_url', '');

        $order = $this->makeOrder();

        SendWhatsAppNotificationJob::dispatchSync($order->id, 'order_placed', 'customer');

        $this->assertDatabaseHas('notification_logs', [
            'channel' => 'whatsapp',
            'template_key' => 'order_placed',
            'status' => 'sent',
        ]);
    }

    public function test_customer_disabled_skips_send_and_log(): void
    {
        Config::set('whatsapp.cloud.enabled', false);
        Config::set('whatsapp.bridge.api_url', '');

        WhatsAppSetting::query()->update(['enabled_customer_notifications' => false]);

        $order = $this->makeOrder();

        SendWhatsAppNotificationJob::dispatchSync($order->id, 'order_placed', 'customer');

        $this->assertSame(0, NotificationLog::query()->count());
    }

    public function test_admin_recipient_receives_separate_send(): void
    {
        Config::set('whatsapp.cloud.enabled', false);
        Config::set('whatsapp.bridge.api_url', '');

        WhatsAppSetting::query()->update([
            'enabled_admin_notifications' => true,
            'admin_recipients' => ['+923001112233'],
        ]);

        $order = $this->makeOrder();

        SendWhatsAppNotificationJob::dispatchSync($order->id, 'admin_new_order', 'admin', '+923001112233');

        $this->assertDatabaseHas('notification_logs', [
            'channel' => 'whatsapp',
            'template_key' => 'admin_new_order',
            'recipient' => '+923001112233',
            'status' => 'sent',
        ]);
    }

    public function test_cloud_template_request_when_configured(): void
    {
        Config::set('whatsapp.cloud.enabled', true);
        Config::set('whatsapp.cloud.token', 'test-token');
        Config::set('whatsapp.cloud.phone_number_id', '123456789');
        Config::set('whatsapp.cloud.templates.order_placed.name', 'order_placed_v1');
        Config::set('whatsapp.cloud.templates.order_placed.language', 'en_US');

        WhatsAppTemplate::query()->where('key', 'order_placed')->update([
            'cloud_template_name' => 'order_placed_v1',
            'cloud_template_language' => 'en_US',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
        ]);

        $order = $this->makeOrder();

        SendWhatsAppNotificationJob::dispatchSync($order->id, 'order_placed', 'customer');

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_contains((string) $request->url(), 'graph.facebook.com')
                && ($data['type'] ?? null) === 'template'
                && ($data['template']['name'] ?? null) === 'order_placed_v1';
        });
    }

    public function test_payment_paid_dispatches_payment_received_job(): void
    {
        Config::set('whatsapp.cloud.enabled', false);
        Config::set('whatsapp.bridge.api_url', '');

        $order = $this->makeOrder([
            'payment_status' => PaymentStatus::Pending,
        ]);

        Bus::fake();

        $order->update(['payment_status' => PaymentStatus::Paid]);

        Bus::assertDispatched(SendWhatsAppNotificationJob::class, function (SendWhatsAppNotificationJob $job): bool {
            return $job->templateKey === 'payment_received' && $job->audience === 'customer';
        });
    }

    public function test_status_change_dispatches_shipped_template(): void
    {
        Config::set('whatsapp.cloud.enabled', false);
        Config::set('whatsapp.bridge.api_url', '');

        $order = $this->makeOrder([
            'status' => OrderStatus::Processing,
        ]);

        Bus::fake();

        $order->update(['status' => OrderStatus::Shipped]);

        Bus::assertDispatched(SendWhatsAppNotificationJob::class, function (SendWhatsAppNotificationJob $job): bool {
            return $job->templateKey === 'order_shipped' && $job->audience === 'customer';
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(array $overrides = []): Order
    {
        $defaults = [
            'order_number' => 'TR-TEST-'.uniqid(),
            'user_id' => null,
            'guest_email' => 'g@example.com',
            'guest_token' => null,
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
            'payment_gateway' => 'cod',
            'coupon_id' => null,
            'subtotal' => '100.00',
            'discount_total' => '0.00',
            'shipping_total' => '200.00',
            'cod_fee' => '0.00',
            'grand_total' => '300.00',
            'shipping_address_snapshot' => [
                'full_name' => 'Casey',
                'phone' => '03001234567',
                'line1' => 'Road 1',
                'city' => 'Karachi',
            ],
            'billing_address_snapshot' => null,
            'customer_notes' => null,
        ];

        /** @var Order $order */
        $order = Order::query()->create(array_merge($defaults, $overrides));

        return $order;
    }
}
