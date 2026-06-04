<?php

namespace App\Console\Commands;

use App\Models\StorefrontSetting;
use App\Support\Images\ResponsiveImageGenerator;
use Illuminate\Console\Command;

class GenerateHeroImageVariants extends Command
{
    protected $signature = 'storefront:generate-hero-variants
        {--force : Regenerate even if variants already exist}';

    protected $description = 'Generate responsive WebP variants for the storefront hero (LCP) image';

    public function handle(ResponsiveImageGenerator $generator): int
    {
        if (! $generator->isSupported()) {
            $this->error('GD WebP support is not available on this PHP build. Aborting.');

            return self::FAILURE;
        }

        $settings = StorefrontSetting::query()->first();
        if (! $settings || blank($settings->hero_image_url)) {
            $this->info('No stored hero image to process.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && filled($settings->hero_image_variants)) {
            $this->info('Hero image variants already exist. Use --force to regenerate.');

            return self::SUCCESS;
        }

        $meta = $generator->generate($settings->hero_image_url, ResponsiveImageGenerator::HERO_WIDTHS);

        if ($meta === null) {
            $this->warn('Could not generate hero variants (external URL or unreadable image).');

            return self::SUCCESS;
        }

        $settings->forceFill([
            'hero_image_width' => $meta['width'],
            'hero_image_height' => $meta['height'],
            'hero_image_variants' => $meta['variants'],
        ])->save();

        $this->info('Generated '.count($meta['variants']).' hero image variant(s).');

        return self::SUCCESS;
    }
}
