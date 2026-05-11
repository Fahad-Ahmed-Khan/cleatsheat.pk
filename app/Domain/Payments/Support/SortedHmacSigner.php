<?php

namespace App\Domain\Payments\Support;

/**
 * Shared HMAC signing used by local PK gateways (Easypaisa / JazzCash-style callbacks).
 * Canonical form: sort keys, concatenate values with "|", HMAC-SHA256 with integration secret.
 */
final class SortedHmacSigner
{
    /**
     * @param  array<string, string|int|float>  $fields
     */
    public static function sign(array $fields, string $hashKeyName, string $secret): string
    {
        $copy = $fields;
        unset($copy[$hashKeyName]);
        ksort($copy);
        $values = array_map(static fn ($v) => (string) $v, array_values($copy));
        $payload = implode('|', $values);

        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function verify(array $fields, string $hashKeyName, string $secret): bool
    {
        $provided = (string) ($fields[$hashKeyName] ?? '');
        $expected = self::sign($fields, $hashKeyName, $secret);

        return hash_equals($expected, $provided);
    }
}
