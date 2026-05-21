<?php

namespace App\Listeners;

use App\Domain\Wishlist\WishlistService;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Schema;

class MergeGuestWishlistOnLogin
{
    public function __construct(
        private readonly WishlistService $wishlist,
    ) {}

    public function handle(Login $event): void
    {
        if (! Schema::hasTable('wishlist_items')) {
            return;
        }

        $ids = request()->session()->pull('guest_wishlist_merge', []);
        if (! is_array($ids) || $ids === []) {
            return;
        }

        $this->wishlist->mergeFromProductIds($event->user, $ids);
    }
}
