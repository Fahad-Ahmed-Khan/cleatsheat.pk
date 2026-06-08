<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Support\Images\ResponsiveImageGenerator;
use Illuminate\Console\Command;

class GenerateProductImageVariants extends Command
{
    protected $signature = 'products:generate-image-variants
        {--force : Regenerate variants even if they already exist}
        {--chunk=50 : Number of images to process per batch}';

    protected $description = 'Generate responsive WebP variants for existing product images';

    public function handle(ResponsiveImageGenerator $generator): int
    {
        if (! $generator->isSupported()) {
            $this->error('GD WebP support is not available on this PHP build. Aborting.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $chunk = max(1, (int) $this->option('chunk'));

        $query = ProductImage::query();
        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No product images to check.');

            return self::SUCCESS;
        }

        $this->info("Checking {$total} product image(s)...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $generated = 0;
        $skipped = 0;

        $query->chunkById($chunk, function ($images) use ($generator, $force, &$generated, &$skipped, $bar) {
            foreach ($images as $image) {
                if (! $force && $generator->variantsComplete($image->variants)) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $meta = $generator->generate($image->path);

                if ($meta === null) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $image->forceFill([
                    'width' => $meta['width'],
                    'height' => $meta['height'],
                    'variants' => $meta['variants'],
                ])->save();

                $generated++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Generated variants for {$generated} image(s), skipped {$skipped}.");

        return self::SUCCESS;
    }
}
