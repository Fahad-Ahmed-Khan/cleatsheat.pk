<?php

namespace App\Domain\Catalog;

use App\Enums\ShoeType;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared filter/sort application for shop and search listings.
 */
final class ProductListFilterApplicator
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function apply(Builder $query, array $filters, bool $includeCategoryIds = true): void
    {
        if ($includeCategoryIds) {
            $categoryIds = array_values(array_filter(array_map('intval', (array) ($filters['category_ids'] ?? []))));
            if ($categoryIds !== []) {
                $query->whereIn('category_id', $categoryIds);
            }
        }

        $brandIds = array_values(array_filter(array_map('intval', (array) ($filters['brand_ids'] ?? []))));
        if ($brandIds !== []) {
            $query->whereIn('brand_id', $brandIds);
        }

        $colorIds = array_values(array_filter(array_map('intval', (array) ($filters['color_ids'] ?? []))));
        if ($colorIds !== []) {
            $query->whereHas('variants', fn (Builder $vq) => $vq->whereIn('color_id', $colorIds));
        }

        $gender = $filters['gender'] ?? null;
        if (is_string($gender) && $gender !== '') {
            $query->where('gender', $gender);
        }

        $type = $filters['type'] ?? null;
        $typeList = $this->normalizeStringList($type);
        if ($typeList !== []) {
            $allowed = array_map(static fn (ShoeType $c): string => $c->value, ShoeType::cases());
            $typeList = array_values(array_intersect($typeList, $allowed));
            if ($typeList !== []) {
                $query->whereIn('shoe_type', $typeList);
            }
        }

        $priceMin = $filters['price_min'] ?? null;
        if ($priceMin !== null && $priceMin !== '') {
            $query->whereHas('variants', fn (Builder $vq) => $vq->where('price', '>=', (float) $priceMin));
        }

        $priceMax = $filters['price_max'] ?? null;
        if ($priceMax !== null && $priceMax !== '') {
            $query->whereHas('variants', fn (Builder $vq) => $vq->where('price', '<=', (float) $priceMax));
        }

        $sizeLabel = $filters['size'] ?? null;
        if (is_string($sizeLabel) && $sizeLabel !== '') {
            $query->whereHas('variants.sizes', fn (Builder $sq) => $sq
                ->where('size_label', $sizeLabel)
                ->where('stock_qty', '>', 0));
        }

        $sizeUk = $filters['size_uk'] ?? null;
        $sizeUkList = $this->normalizeStringList($sizeUk);
        if ($sizeUkList !== []) {
            $query->whereHas('variants.sizes', fn (Builder $sq) => $sq
                ->whereIn('uk_size', $sizeUkList)
                ->where('stock_qty', '>', 0));
        }

        $availability = $filters['availability'] ?? null;
        if ($availability === 'in_stock') {
            $query->whereHas('variants.sizes', fn (Builder $sq) => $sq->where('stock_qty', '>', 0));
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applySort(Builder $query, array $filters): void
    {
        $sort = $filters['sort'] ?? null;
        if ($sort === 'price_asc') {
            $query->orderBy(
                ProductVariant::query()->select('price')->whereColumn('product_id', 'products.id')->orderBy('price')->limit(1),
                'asc'
            );
        } elseif ($sort === 'price_desc') {
            $query->orderBy(
                ProductVariant::query()->select('price')->whereColumn('product_id', 'products.id')->orderBy('price')->limit(1),
                'desc'
            );
        } elseif ($sort === 'newest') {
            $query->latest();
        }
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                static fn (mixed $v): ?string => is_string($v) || is_numeric($v) ? trim((string) $v) : null,
                $value
            ), static fn (?string $v): bool => $v !== null && $v !== ''));
        }

        if (is_string($value) || is_numeric($value)) {
            $s = trim((string) $value);

            return $s === '' ? [] : [$s];
        }

        return [];
    }
}
