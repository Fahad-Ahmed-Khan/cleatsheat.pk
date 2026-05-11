<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['order_id', 'gateway', 'status', 'amount', 'external_id', 'meta', 'paid_at'];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'meta' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<PaymentAttempt, $this> */
    public function attempts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }
}
