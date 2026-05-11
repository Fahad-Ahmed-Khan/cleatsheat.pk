<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'min_cart_total', 'starts_at', 'ends_at',
        'max_redemptions', 'redemptions_count', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'decimal:2',
            'min_cart_total' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_redemptions' => 'integer',
            'redemptions_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
