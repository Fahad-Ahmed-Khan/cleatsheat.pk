<?php

namespace App\Domain\Shipping\Trax;

use App\Models\CourierAccount;

final class TraxTokenResolver
{
    public static function forCourierAccount(?CourierAccount $account): string
    {
        $creds = $account?->credentials ?? [];

        return trim((string) ($creds['api_token'] ?? ''));
    }
}

