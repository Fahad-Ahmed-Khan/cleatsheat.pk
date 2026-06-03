<?php

namespace Tests\Unit\Support;

use App\Support\Storage\PublicAssetUrl;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PublicAssetUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.url', 'https://tryinotech.cloud');
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
}
