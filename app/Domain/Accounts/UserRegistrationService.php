<?php

namespace App\Domain\Accounts;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;

final class UserRegistrationService
{
    public function registerCustomer(string $name, string $email, string $plainPassword): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => mb_strtolower($email),
            'password' => Hash::make($plainPassword),
            'role' => UserRole::Customer,
            'locale' => 'en',
        ]);

        event(new Registered($user));

        return $user;
    }
}
