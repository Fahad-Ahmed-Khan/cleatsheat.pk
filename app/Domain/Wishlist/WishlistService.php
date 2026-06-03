<?php

namespace App\Domain\Wishlist;

use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

final class WishlistService
{
    /**
     * @return list<int>
     */
    public function productIdsForUser(User $user): array
    {
        if (! Schema::hasTable('wishlist_items')) {
            return [];
        }

        return WishlistItem::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function add(User $user, int $productId): void
    {
        Product::query()->whereKey($productId)->firstOrFail();

        WishlistItem::query()->firstOrCreate([
            'user_id' => $user->id,
            'product_id' => $productId,
        ]);
    }

    public function remove(User $user, int $productId): void
    {
        WishlistItem::query()
            ->where('user_id', $user->id)
            ->where('product_id', $productId)
            ->delete();
    }

    public function toggle(User $user, int $productId): bool
    {
        $exists = WishlistItem::query()
            ->where('user_id', $user->id)
            ->where('product_id', $productId)
            ->exists();

        if ($exists) {
            $this->remove($user, $productId);

            return false;
        }

        $this->add($user, $productId);

        return true;
    }

    /**
     * @param  list<int|string>  $productIds
     */
    public function mergeFromProductIds(User $user, array $productIds): void
    {
        foreach ($productIds as $id) {
            $productId = (int) $id;
            if ($productId <= 0) {
                continue;
            }
            if (! Product::query()->whereKey($productId)->exists()) {
                continue;
            }
            WishlistItem::query()->firstOrCreate([
                'user_id' => $user->id,
                'product_id' => $productId,
            ]);
        }
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginateProductsForUser(User $user, int $perPage = 12): LengthAwarePaginator
    {
        $productIds = WishlistItem::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->pluck('product_id');

        if ($productIds->isEmpty()) {
            return Product::query()->whereRaw('1 = 0')->paginate($perPage);
        }

        return Product::query()
            ->whereIn('id', $productIds)
            ->with(['category', 'brand', 'variants' => fn ($q) => $q->orderBy('id')])
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
