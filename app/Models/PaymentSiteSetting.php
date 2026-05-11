<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSiteSetting extends Model
{
    protected $fillable = ['fallback_online_failed_to_cod'];

    protected function casts(): array
    {
        return [
            'fallback_online_failed_to_cod' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'fallback_online_failed_to_cod' => true,
        ]);
    }
}
