<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderReturn extends Model
{
    protected $fillable = [
        'order_id',
        'reason',
        'restock',
        'created_by',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'restock' => 'boolean',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<OrderReturnItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }
}

