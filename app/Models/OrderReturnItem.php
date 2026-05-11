<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturnItem extends Model
{
    protected $fillable = [
        'order_return_id',
        'order_item_id',
        'qty',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
        ];
    }

    /** @return BelongsTo<OrderReturn, $this> */
    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'order_return_id');
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}

