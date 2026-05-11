<?php

namespace App\Listeners;

use App\Domain\Checkout\CartService;
use Illuminate\Auth\Events\Login;

class MergeGuestCartOnLogin
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function handle(Login $event): void
    {
        $this->cartService->mergeGuestCartIntoUser($event->user, request());
    }
}
