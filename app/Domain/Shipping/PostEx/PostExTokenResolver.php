<?php

namespace App\Domain\Shipping\PostEx;

use App\Models\Courier;
use App\Models\CourierAccount;

final class PostExTokenResolver
{
    /**
     * Prefer the linked courier account’s token; if empty, use the default active PostEx account.
     * This covers shipments created before credentials were saved, sandbox bookings, or missing courier_account_id.
     */
    public static function forCourierAccount(?CourierAccount $account): string
    {
        $direct = (string) ($account?->credentials['api_token'] ?? '');
        if ($direct !== '') {
            return $direct;
        }

        return self::defaultActiveToken();
    }

    public static function defaultActiveToken(): string
    {
        $courier = Courier::query()->where('code', 'postex')->first();
        if ($courier === null) {
            return '';
        }

        $account = CourierAccount::query()
            ->where('courier_id', $courier->id)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first()
            ?? CourierAccount::query()
                ->where('courier_id', $courier->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

        return (string) ($account?->credentials['api_token'] ?? '');
    }
}
