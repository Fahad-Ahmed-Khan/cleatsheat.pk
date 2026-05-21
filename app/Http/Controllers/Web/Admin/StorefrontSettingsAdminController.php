<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateStorefrontSettingsRequest;
use App\Models\StorefrontSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontSettingsAdminController extends Controller
{
    public function edit(): Response
    {
        $s = StorefrontSetting::current();

        return Inertia::render('Admin/Storefront/Settings', [
            'settings' => $s->only([
                'site_name',
                'logo_url',
                'logo_dark_url',
                'favicon_url',
                'primary_color',
                'secondary_color',
                'primary_foreground_color',
                'hero_title',
                'hero_subtitle',
                'hero_badge',
                'hero_image_url',
                'hero_cta_label',
                'hero_cta_url',
                'promo_banner_image_url',
                'promo_banner_link_url',
                'promo_banner_title',
                'default_meta_title',
                'default_meta_description',
                'default_og_image_url',
                'twitter_site',
                'ga4_enabled',
                'ga4_measurement_id',
                'gtm_enabled',
                'gtm_container_id',
                'meta_pixel_enabled',
                'meta_pixel_id',
                'tiktok_pixel_enabled',
                'tiktok_pixel_id',
            ]) + [
                'ga4_enabled' => (bool) $s->ga4_enabled,
                'gtm_enabled' => (bool) $s->gtm_enabled,
                'meta_pixel_enabled' => (bool) $s->meta_pixel_enabled,
                'tiktok_pixel_enabled' => (bool) $s->tiktok_pixel_enabled,
            ],
        ]);
    }

    public function update(UpdateStorefrontSettingsRequest $request): RedirectResponse
    {
        $s = StorefrontSetting::current();
        $data = collect($request->validated())
            ->except(['logo', 'logo_dark', 'favicon', 'hero_image', 'promo_banner_image', 'default_og_image'])
            ->all();

        foreach ($this->uploadFieldMap() as $fileKey => $urlKey) {
            $file = $request->file($fileKey);
            if ($file instanceof UploadedFile) {
                $data[$urlKey] = $this->storePublicImage($file);
            }
        }

        $s->fill($data);
        $s->save();

        return redirect()->route('admin.storefront-settings.edit')->with('success', 'Storefront settings saved.');
    }

    /**
     * @return array<string, string>
     */
    private function uploadFieldMap(): array
    {
        return [
            'logo' => 'logo_url',
            'logo_dark' => 'logo_dark_url',
            'favicon' => 'favicon_url',
            'hero_image' => 'hero_image_url',
            'promo_banner_image' => 'promo_banner_image_url',
            'default_og_image' => 'default_og_image_url',
        ];
    }

    private function storePublicImage(UploadedFile $file): string
    {
        $path = $file->store('storefront', 'public');

        return Storage::disk('public')->url($path);
    }
}
