<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    protected $fillable = [
        'payment_id', 'order_id', 'gateway_code', 'attempt_number', 'status',
        'amount', 'external_reference', 'request_snapshot', 'response_snapshot', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'request_snapshot' => 'array',
            'response_snapshot' => 'array',
        ];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
