<?php

namespace Tests\Unit\Models;

use App\Models\StorefrontSetting;
use App\Support\Storage\PublicAssetUrl;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class StorefrontSettingAssetUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('filesystems.disks.public', [
            'driver' => 's3',
            'bucket' => 'tryino-ecom-public',
            'url' => 'https://cdn.tryinotech.cloud',
            'visibility' => 'public',
        ]);
    }

    public function test_branding_payload_resolves_relative_paths_via_current_disk(): void
    {
        $setting = new StorefrontSetting([
            'logo_url' => 'storefront/logo.png',
            'logo_dark_url' => 'storefront/logo-dark.png',
            'favicon_url' => 'storefront/favicon.ico',
        ]);

        $disk = Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->shouldReceive('url')
            ->with('storefront/logo.png')
            ->andReturn('https://cdn.tryinotech.cloud/storefront/logo.png');
        $disk->shouldReceive('url')
            ->with('storefront/logo-dark.png')
            ->andReturn('https://cdn.tryinotech.cloud/storefront/logo-dark.png');
        $disk->shouldReceive('url')
            ->with('storefront/favicon.ico')
            ->andReturn('https://cdn.tryinotech.cloud/storefront/favicon.ico');
        \Illuminate\Support\Facades\Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        $payload = $setting->toBrandingPayload();

        $this->assertSame('https://cdn.tryinotech.cloud/storefront/logo.png', $payload['logo_url']);
        $this->assertSame('https://cdn.tryinotech.cloud/storefront/logo-dark.png', $payload['logo_dark_url']);
        $this->assertSame('https://cdn.tryinotech.cloud/storefront/favicon.ico', $payload['favicon_url']);
    }

    public function test_normalize_stored_asset_paths_converts_legacy_full_urls(): void
    {
        $normalized = StorefrontSetting::normalizeStoredAssetPaths([
            'logo_url' => 'https://tryinotech.cloud/storage/storefront/logo.png',
            'hero_image_url' => 'storefront/hero.jpg',
        ]);

        $this->assertSame('storefront/logo.png', $normalized['logo_url']);
        $this->assertSame('storefront/hero.jpg', $normalized['hero_image_url']);
    }

    public function test_resolve_rewrites_legacy_storefront_url_to_cdn(): void
    {
        $disk = Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->shouldReceive('url')
            ->once()
            ->with('storefront/logo.png')
            ->andReturn('https://cdn.tryinotech.cloud/storefront/logo.png');
        \Illuminate\Support\Facades\Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        $url = PublicAssetUrl::resolve('https://tryinotech.cloud/storage/storefront/logo.png');

        $this->assertSame('https://cdn.tryinotech.cloud/storefront/logo.png', $url);
    }
}
