<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateWhatsAppSettingsRequest;
use App\Models\WhatsAppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppSettingsAdminController extends Controller
{
    public function edit(): Response
    {
        $settings = WhatsAppSetting::current();

        $lines = $settings->admin_recipients ?? [];
        $lines = is_array($lines) ? $lines : [];

        return Inertia::render('Admin/WhatsApp/Settings', [
            'settings' => [
                'enabled_customer_notifications' => (bool) $settings->enabled_customer_notifications,
                'enabled_admin_notifications' => (bool) $settings->enabled_admin_notifications,
                'enabled_cod_confirmation' => (bool) $settings->enabled_cod_confirmation,
                'enabled_shipment_status_customer_alerts' => (bool) $settings->enabled_shipment_status_customer_alerts,
                'enabled_pickup_notices' => (bool) $settings->enabled_pickup_notices,
                'pickup_notice_time' => (string) ($settings->pickup_notice_time ?? '11:00'),
                'cloud_webhook_verify_token' => (string) ($settings->cloud_webhook_verify_token ?? ''),
                'marketing_opt_out_keyword' => (string) ($settings->marketing_opt_out_keyword ?? 'STOP'),
                'promotional_throttle_per_minute' => (int) ($settings->promotional_throttle_per_minute ?? 20),
                'admin_recipients_text' => implode("\n", array_map(static fn (mixed $v): string => (string) $v, $lines)),
            ],
            'webhook_url' => url('/webhooks/whatsapp'),
            'cloud_enabled' => (bool) config('whatsapp.cloud.enabled', false),
        ]);
    }

    public function update(UpdateWhatsAppSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $settings = WhatsAppSetting::current();
        $settings->enabled_customer_notifications = (bool) ($data['enabled_customer_notifications'] ?? false);
        $settings->enabled_admin_notifications = (bool) ($data['enabled_admin_notifications'] ?? false);
        $settings->enabled_cod_confirmation = (bool) ($data['enabled_cod_confirmation'] ?? false);
        $settings->enabled_shipment_status_customer_alerts = (bool) ($data['enabled_shipment_status_customer_alerts'] ?? false);
        $settings->enabled_pickup_notices = (bool) ($data['enabled_pickup_notices'] ?? false);
        $settings->pickup_notice_time = $data['pickup_notice_time'] ?? '11:00';

        $tok = trim((string) ($data['cloud_webhook_verify_token'] ?? ''));
        if ($tok === '' && (string) $settings->cloud_webhook_verify_token === '') {
            $tok = (string) Str::random(40);
        }
        if ($tok !== '') {
            $settings->cloud_webhook_verify_token = $tok;
        }

        $settings->marketing_opt_out_keyword = strtoupper((string) ($data['marketing_opt_out_keyword'] ?? 'STOP'));
        $settings->promotional_throttle_per_minute = (int) ($data['promotional_throttle_per_minute'] ?? 20);
        $settings->admin_recipients = $data['admin_recipients'] ?? [];
        $settings->save();

        return redirect()->route('admin.whatsapp-settings.edit')->with('status', 'WhatsApp settings saved.');
    }
}
