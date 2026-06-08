<?php

namespace App\Observers;

use App\Domain\Catalog\ProductSearchIndexBuilder;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantSize;
use Illuminate\Database\Eloquent\Model;

class ProductSearchIndexObserver
{
    /** @var list<string> */
    private static array $relevantProductFields = [
        'name', 'slug', 'description', 'meta_title', 'gender', 'shoe_type',
        'features', 'brand_id', 'category_id', 'is_active',
    ];

    public function __construct(private ProductSearchIndexBuilder $builder) {}

    public function saved(Model $model): void
    {
        if ($model instanceof Product) {
            $this->onProductSaved($model);

            return;
        }

        if ($model instanceof ProductVariant) {
            $this->builder->rebuildProduct((int) $model->product_id);

            return;
        }

        if ($model instanceof Brand) {
            if (! $model->wasRecentlyCreated && ! $model->wasChanged(['name', 'slug'])) {
                return;
            }
            $this->rebuildForProductIds(
                Product::query()->where('brand_id', $model->id)->pluck('id')->all()
            );

            return;
        }

        if ($model instanceof Category) {
            if (! $model->wasRecentlyCreated && ! $model->wasChanged(['name', 'slug'])) {
                return;
            }
            $this->rebuildForProductIds(
                Product::query()->where('category_id', $model->id)->pluck('id')->all()
            );

            return;
        }

        if ($model instanceof Color) {
            if (! $model->wasRecentlyCreated && ! $model->wasChanged(['name', 'slug'])) {
                return;
            }
            $productIds = ProductVariant::query()
                ->where('color_id', $model->id)
                ->pluck('product_id')
                ->unique()
                ->all();
            $this->rebuildForProductIds($productIds);

            return;
        }

        if ($model instanceof VariantSize) {
            $this->rebuildForVariant((int) $model->product_variant_id);
        }
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof ProductVariant) {
            $this->builder->rebuildProduct((int) $model->product_id);

            return;
        }

        if ($model instanceof VariantSize) {
            $this->rebuildForVariant((int) $model->product_variant_id);
        }
    }

    private function rebuildForVariant(int $variantId): void
    {
        $productId = ProductVariant::query()->whereKey($variantId)->value('product_id');
        if ($productId !== null) {
            $this->builder->rebuildProduct((int) $productId);
        }
    }

    private function onProductSaved(Product $product): void
    {
        if (! $product->wasRecentlyCreated && ! $product->wasChanged(self::$relevantProductFields)) {
            return;
        }

        $this->builder->rebuildProduct((int) $product->id);
    }

    /**
     * @param  list<int|string>  $productIds
     */
    private function rebuildForProductIds(array $productIds): void
    {
        foreach ($productIds as $id) {
            $this->builder->rebuildProduct((int) $id);
        }
    }
}
