<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function manageShipping(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }
}
