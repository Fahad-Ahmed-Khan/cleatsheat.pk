<?php

namespace App\Support\Bargain;

use App\Models\User;

final class PhoneNormalizer
{
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '92')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+92'.substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '+92'.$digits;
        }

        if (str_starts_with($digits, '1') && strlen($digits) >= 11) {
            return '+'.$digits;
        }

        return '+'.$digits;
    }

    public static function customerKey(?User $user, ?string $normalizedPhone): string
    {
        if ($user !== null) {
            return 'u:'.$user->id;
        }

        $phone = self::normalize($normalizedPhone ?? '');
        if ($phone === null) {
            throw new \InvalidArgumentException('A valid phone number is required for bargaining.');
        }

        return 'p:'.$phone;
    }
}
