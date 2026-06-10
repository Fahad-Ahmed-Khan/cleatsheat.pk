<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateStorefrontSettingsRequest;
use App\Models\StorefrontSetting;
use App\Support\Images\ResponsiveImageGenerator;
use App\Support\Storage\PublicAssetUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontSettingsAdminController extends Controller
{
    public function edit(): Response
    {
        $s = StorefrontSetting::current();

        return Inertia::render('Admin/Storefront/Settings', [
            'settings' => $s->toAdminSettingsPayload() + [
                'ga4_enabled' => (bool) $s->ga4_enabled,
                'gtm_enabled' => (bool) $s->gtm_enabled,
                'meta_pixel_enabled' => (bool) $s->meta_pixel_enabled,
                'tiktok_pixel_enabled' => (bool) $s->tiktok_pixel_enabled,
            ],
        ]);
    }

    public function update(UpdateStorefrontSettingsRequest $request, ResponsiveImageGenerator $images): RedirectResponse
    {
        $s = StorefrontSetting::current();
        $previousHeroUrl = $s->hero_image_url;

        $data = collect($request->validated())
            ->except(['logo', 'logo_dark', 'favicon', 'hero_image', 'promo_banner_image', 'default_og_image'])
            ->all();

        foreach ($this->uploadFieldMap() as $fileKey => $urlKey) {
            $file = $request->file($fileKey);
            if ($file instanceof UploadedFile) {
                $data[$urlKey] = $this->storePublicImage($file);
            }
        }

        $data = StorefrontSetting::normalizeStoredAssetPaths($data);

        $s->fill($data);
        $s->save();

        $this->syncHeroVariants($s, $previousHeroUrl, $images);

        return redirect()->route('admin.storefront-settings.edit')->with('success', 'Storefront settings saved.');
    }

    /**
     * Regenerate the responsive WebP variants used by the LCP hero image whenever
     * the hero image changes, and clear stale metadata when it is removed/swapped.
     */
    private function syncHeroVariants(
        StorefrontSetting $s,
        ?string $previousHeroUrl,
        ResponsiveImageGenerator $images,
    ): void {
        $heroUrl = $s->hero_image_url;

        if ($heroUrl === $previousHeroUrl && filled($s->hero_image_variants)) {
            return;
        }

        if ($previousHeroUrl && $previousHeroUrl !== $heroUrl) {
            $images->purge($previousHeroUrl, ResponsiveImageGenerator::HERO_WIDTHS);
        }

        $meta = filled($heroUrl) && $images->isSupported()
            ? $images->generate($heroUrl, ResponsiveImageGenerator::HERO_WIDTHS)
            : null;

        $s->forceFill([
            'hero_image_width' => $meta['width'] ?? null,
            'hero_image_height' => $meta['height'] ?? null,
            'hero_image_variants' => $meta['variants'] ?? null,
        ])->save();
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

        return PublicAssetUrl::normalizeForStorage($path) ?? $path;
    }
}
