<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateStorefrontAssistantSettingsRequest;
use App\Models\StorefrontAssistantSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontAssistantSettingsAdminController extends Controller
{
    public function edit(): Response
    {
        $s = StorefrontAssistantSetting::current();

        return Inertia::render('Admin/StorefrontAssistant/Settings', [
            'settings' => [
                'enabled' => (bool) $s->enabled,
                'preview_enabled' => (bool) $s->preview_enabled,
                'delay_seconds' => (int) $s->delay_seconds,
                'snooze_days' => (int) $s->snooze_days,
                'allowed_routes_text' => implode("\n", array_map(
                    static fn (mixed $v): string => (string) $v,
                    is_array($s->allowed_routes_json) ? $s->allowed_routes_json : []
                )),
                'ui' => is_array($s->ui_json) ? $s->ui_json : [],
                'steps' => is_array($s->steps_json) ? $s->steps_json : [],
                'mapping' => is_array($s->mapping_json) ? $s->mapping_json : [],
            ],
        ]);
    }

    public function update(UpdateStorefrontAssistantSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $s = StorefrontAssistantSetting::current();
        $s->enabled = (bool) ($data['enabled'] ?? false);
        $s->preview_enabled = (bool) ($data['preview_enabled'] ?? false);
        $s->delay_seconds = (int) ($data['delay_seconds'] ?? 0);
        $s->snooze_days = (int) ($data['snooze_days'] ?? 0);
        $s->allowed_routes_json = $data['allowed_routes'] ?? [];
        $s->ui_json = $data['ui'] ?? [];
        $s->steps_json = $data['steps'] ?? [];
        $s->mapping_json = $data['mapping'] ?? [];
        $s->save();

        return redirect()->route('admin.storefront-assistant.edit')->with('status', 'Storefront Assistant settings saved.');
    }
}

