<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigratePublicDiskCommandTest extends TestCase
{
    public function test_fails_when_public_disk_is_local(): void
    {
        Config::set('filesystems.disks.public.driver', 'local');

        $this->artisan('storage:migrate-public-disk')
            ->assertFailed();
    }

    public function test_dry_run_lists_files_without_uploading(): void
    {
        Storage::fake('public');
        Config::set('filesystems.disks.public.driver', 's3');

        $source = storage_path('app/migrate-test-source');
        mkdir($source.'/products', 0755, true);
        file_put_contents($source.'/products/test.jpg', 'fake-image');

        try {
            $this->artisan('storage:migrate-public-disk', [
                '--source' => $source,
                '--dry-run' => true,
            ])
                ->expectsOutputToContain('would upload: products/test.jpg')
                ->assertSuccessful();

            $this->assertFalse(Storage::disk('public')->exists('products/test.jpg'));
        } finally {
            @unlink($source.'/products/test.jpg');
            @rmdir($source.'/products');
            @rmdir($source);
        }
    }

    public function test_uploads_missing_files_to_public_disk(): void
    {
        Storage::fake('public');
        Config::set('filesystems.disks.public.driver', 's3');

        $source = storage_path('app/migrate-test-source');
        mkdir($source.'/storefront', 0755, true);
        file_put_contents($source.'/storefront/logo.png', 'logo');

        try {
            Artisan::call('storage:migrate-public-disk', ['--source' => $source]);

            $this->assertTrue(Storage::disk('public')->exists('storefront/logo.png'));
        } finally {
            @unlink($source.'/storefront/logo.png');
            @rmdir($source.'/storefront');
            @rmdir($source);
        }
    }
}
