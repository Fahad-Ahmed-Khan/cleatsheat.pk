<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAuditEvent extends Model
{
    protected $fillable = [
        'order_id',
        'actor_user_id',
        'event_type',
        'changes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

