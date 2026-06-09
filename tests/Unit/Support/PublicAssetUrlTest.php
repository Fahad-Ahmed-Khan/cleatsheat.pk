<?php

namespace Tests\Unit\Support;

use App\Support\Storage\PublicAssetUrl;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class PublicAssetUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.url', 'https://tryinotech.cloud');
        Config::set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => 'https://tryinotech.cloud/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ]);
    }

    public function test_resolve_rewrites_legacy_local_full_url(): void
    {
        $url = PublicAssetUrl::resolve(
            'http://tryino-ecom.test/storage/products/abc.jpg'
        );

        $this->assertSame('https://tryinotech.cloud/storage/products/abc.jpg', $url);
    }

    public function test_resolve_relative_disk_path(): void
    {
        $url = PublicAssetUrl::resolve('products/abc.jpg');

        $this->assertSame('https://tryinotech.cloud/storage/products/abc.jpg', $url);
    }

    public function test_normalize_for_storage_strips_local_domain(): void
    {
        $path = PublicAssetUrl::normalizeForStorage(
            'http://tryino-ecom.test/storage/products/abc.jpg'
        );

        $this->assertSame('products/abc.jpg', $path);
    }

    public function test_resolve_keeps_external_cdn_url(): void
    {
        $cdn = 'https://cdn.example.com/bucket/photo.jpg';

        $this->assertSame($cdn, PublicAssetUrl::resolve($cdn));
    }

    public function test_resolve_uses_configured_cdn_base_for_relative_paths(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('url')
            ->once()
            ->with('products/abc.jpg')
            ->andReturn('https://cdn.tryinotech.cloud/products/abc.jpg');
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        $url = PublicAssetUrl::resolve('products/abc.jpg');

        $this->assertSame('https://cdn.tryinotech.cloud/products/abc.jpg', $url);
    }

    public function test_normalize_for_storage_strips_configured_cdn_url(): void
    {
        Config::set('filesystems.disks.public', [
            'driver' => 's3',
            'bucket' => 'tryino-ecom-public',
            'url' => 'https://cdn.tryinotech.cloud',
            'visibility' => 'public',
        ]);

        $path = PublicAssetUrl::normalizeForStorage(
            'https://cdn.tryinotech.cloud/products/abc.jpg'
        );

        $this->assertSame('products/abc.jpg', $path);
    }

    public function test_normalize_for_storage_strips_b2_path_style_url(): void
    {
        Config::set('filesystems.disks.public', [
            'driver' => 's3',
            'bucket' => 'tryino-ecom-public',
            'url' => 'https://cdn.tryinotech.cloud',
            'visibility' => 'public',
        ]);

        $path = PublicAssetUrl::normalizeForStorage(
            'https://s3.us-west-004.backblazeb2.com/tryino-ecom-public/products/abc.jpg'
        );

        $this->assertSame('products/abc.jpg', $path);
    }
}
