<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\StorefrontSetting;
use App\Support\Storage\PublicAssetUrl;
use Illuminate\Console\Command;

class NormalizePublicStoragePathsCommand extends Command
{
    protected $signature = 'storage:normalize-paths';

    protected $description = 'Convert stored public asset URLs to disk-relative paths (products, storefront, brands)';

    public function handle(): int
    {
        $updated = 0;

        ProductImage::query()->orderBy('id')->chunkById(200, function ($images) use (&$updated) {
            foreach ($images as $image) {
                $normalized = PublicAssetUrl::normalizeForStorage($image->path);
                if ($normalized === null || $normalized === $image->path) {
                    continue;
                }

                $image->update(['path' => $normalized]);
                $updated++;
            }
        });

        $videoUpdated = 0;
        Product::query()->whereNotNull('video_url')->orderBy('id')->chunkById(100, function ($products) use (&$videoUpdated) {
            foreach ($products as $product) {
                $normalized = PublicAssetUrl::normalizeForStorage($product->video_url);
                if ($normalized === null || $normalized === $product->video_url) {
                    continue;
                }

                $product->update(['video_url' => $normalized]);
                $videoUpdated++;
            }
        });

        $storefrontUpdated = 0;
        $storefront = StorefrontSetting::query()->first();
        if ($storefront) {
            $changes = [];
            foreach (StorefrontSetting::PUBLIC_ASSET_COLUMNS as $column) {
                $current = $storefront->{$column};
                if (! is_string($current) || $current === '') {
                    continue;
                }

                $normalized = PublicAssetUrl::normalizeForStorage($current);
                if ($normalized === null || $normalized === $current) {
                    continue;
                }

                $changes[$column] = $normalized;
            }

            if ($changes !== []) {
                $storefront->update($changes);
                $storefrontUpdated = count($changes);
            }
        }

        $brandUpdated = 0;
        Brand::query()->whereNotNull('logo_path')->orderBy('id')->chunkById(100, function ($brands) use (&$brandUpdated) {
            foreach ($brands as $brand) {
                $normalized = PublicAssetUrl::normalizeForStorage($brand->logo_path);
                if ($normalized === null || $normalized === $brand->logo_path) {
                    continue;
                }

                $brand->update(['logo_path' => $normalized]);
                $brandUpdated++;
            }
        });

        $this->info("Normalized {$updated} product image path(s), {$videoUpdated} video URL(s), {$storefrontUpdated} storefront asset(s), and {$brandUpdated} brand logo path(s).");

        return self::SUCCESS;
    }
}
