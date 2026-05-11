<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateWhatsAppSettingsRequest;
use App\Models\WhatsAppSetting;
use Illuminate\Http\RedirectResponse;
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
                'admin_recipients_text' => implode("\n", array_map(static fn (mixed $v): string => (string) $v, $lines)),
            ],
        ]);
    }

    public function update(UpdateWhatsAppSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $settings = WhatsAppSetting::current();
        $settings->enabled_customer_notifications = (bool) ($data['enabled_customer_notifications'] ?? false);
        $settings->enabled_admin_notifications = (bool) ($data['enabled_admin_notifications'] ?? false);
        $settings->admin_recipients = $data['admin_recipients'] ?? [];
        $settings->save();

        return redirect()->route('admin.whatsapp-settings.edit')->with('status', 'WhatsApp settings saved.');
    }
}
