<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAdjustment extends Model
{
    protected $fillable = [
        'order_id',
        'type',
        'value',
        'reason',
        'created_by',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'voided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

