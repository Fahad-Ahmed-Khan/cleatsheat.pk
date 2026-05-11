<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMarketingSettingsRequest;
use App\Models\MarketingSetting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MarketingSettingsAdminController extends Controller
{
    public function edit(): Response
    {
        $s = MarketingSetting::current();

        return Inertia::render('Admin/Marketing/Settings', [
            'settings' => [
                'home_meta_title' => $s->home_meta_title,
                'home_meta_description' => $s->home_meta_description,
                'default_og_image_url' => $s->default_og_image_url,
                'twitter_site' => $s->twitter_site,
                'ga4_enabled' => (bool) $s->ga4_enabled,
                'ga4_measurement_id' => $s->ga4_measurement_id,
                'meta_pixel_enabled' => (bool) $s->meta_pixel_enabled,
                'meta_pixel_id' => $s->meta_pixel_id,
                'tiktok_pixel_enabled' => (bool) $s->tiktok_pixel_enabled,
                'tiktok_pixel_id' => $s->tiktok_pixel_id,
                'robots_mode' => $s->robots_mode ?? 'allow_all',
                'robots_custom' => $s->robots_custom,
            ],
        ]);
    }

    public function update(UpdateMarketingSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $s = MarketingSetting::current();
        $s->fill($data);
        $s->save();

        return redirect()->route('admin.marketing-settings.edit')->with('status', 'Marketing & SEO settings saved.');
    }
}
