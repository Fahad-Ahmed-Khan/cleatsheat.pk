<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Support\Storage\PublicAssetUrl;
use Illuminate\Console\Command;

class NormalizePublicStoragePathsCommand extends Command
{
    protected $signature = 'storage:normalize-paths';

    protected $description = 'Convert product image paths from full URLs to disk-relative paths';

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

        $this->info("Normalized {$updated} product image path(s) and {$videoUpdated} video URL(s).");

        return self::SUCCESS;
    }
}
