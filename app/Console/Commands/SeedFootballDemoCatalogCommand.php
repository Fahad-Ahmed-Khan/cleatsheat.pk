<?php

namespace App\Console\Commands;

use Database\Seeders\FootballShoesDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedFootballDemoCatalogCommand extends Command
{
    protected $signature = 'catalog:seed-football-demo {--fresh : Delete existing football demo catalog first}';

    protected $description = 'Seed demo football-shoes brands, categories, and ~50 products';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->warn('Deleting existing football demo catalog (products first)...');

            DB::transaction(function (): void {
                $parent = DB::table('categories')->where('slug', FootballShoesDemoSeeder::CATEGORY_PARENT_SLUG)->first();
                if (! $parent) {
                    return;
                }

                $childIds = DB::table('categories')->where('parent_id', $parent->id)->pluck('id')->all();
                $categoryIds = array_values(array_filter([$parent->id, ...$childIds]));

                // Delete products under these categories (cascades to variants/images/sizes).
                DB::table('products')->whereIn('category_id', $categoryIds)->delete();

                // Delete child categories then parent category.
                DB::table('categories')->whereIn('id', $childIds)->delete();
                DB::table('categories')->where('id', $parent->id)->delete();

                // Delete demo brands (only the ones we created).
                $brandSlugs = array_map(static fn ($b) => $b['slug'], FootballShoesDemoSeeder::BRANDS);
                DB::table('brands')->whereIn('slug', $brandSlugs)->delete();
            });
        }

        $this->info('Seeding football shoes demo catalog...');
        $this->callSilent('db:seed', ['--class' => FootballShoesDemoSeeder::class]);
        $this->info('Done.');

        $this->line('If you are uploading real images, make sure the storage symlink exists:');
        $this->line('php artisan storage:link');

        return self::SUCCESS;
    }
}

