<?php

namespace App\Console\Commands;

use App\Domain\Catalog\ProductSearchIndexBuilder;
use App\Models\Product;
use Illuminate\Console\Command;

class RebuildProductSearchIndex extends Command
{
    protected $signature = 'catalog:rebuild-search-index
                            {--product= : Rebuild a single product ID}
                            {--missing : Only products with empty search_text (fast safety-net pass)}
                            {--chunk=200 : Chunk size for bulk rebuild}';

    protected $description = 'Rebuild denormalized search_text for catalog products';

    public function handle(ProductSearchIndexBuilder $builder): int
    {
        $productId = $this->option('product');

        if ($productId !== null && $productId !== '') {
            $builder->rebuildProduct((int) $productId);
            $this->info('Rebuilt search index for product #'.$productId);

            return self::SUCCESS;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $query = Product::query()->orderBy('id');

        if ($this->option('missing')) {
            $query->where(function ($q) {
                $q->whereNull('search_text')->orWhere('search_text', '');
            });
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No products need a search index rebuild.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById($chunk, function ($products) use ($builder, $bar) {
            foreach ($products as $product) {
                $builder->rebuildProduct((int) $product->id);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Search index rebuild complete.');

        return self::SUCCESS;
    }
}
