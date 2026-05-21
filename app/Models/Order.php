<?php

namespace App\Models;

use App\Enums\CourierAssignmentMode;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'guest_email', 'guest_token',
        'status', 'payment_status', 'payment_gateway', 'coupon_id',
        'subtotal', 'discount_total', 'shipping_total', 'cod_fee', 'grand_total',
        'shipping_address_snapshot', 'billing_address_snapshot', 'customer_notes',
        'preferred_courier_id', 'courier_assignment',
        'awaiting_confirmation', 'confirmation_sent_at', 'confirmed_at', 'confirmation_channel',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'courier_assignment' => CourierAssignmentMode::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'cod_fee' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'shipping_address_snapshot' => 'array',
            'billing_address_snapshot' => 'array',
            'awaiting_confirmation' => 'boolean',
            'confirmation_sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return BelongsTo<Courier, $this> */
    public function preferredCourier(): BelongsTo
    {
        return $this->belongsTo(Courier::class, 'preferred_courier_id');
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<PaymentStatusHistory, $this> */
    public function paymentStatusHistories(): HasMany
    {
        return $this->hasMany(PaymentStatusHistory::class);
    }

    /** @return HasMany<Shipment, $this> */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /** @return HasMany<OrderAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(OrderAdjustment::class);
    }

    /** @return HasMany<OrderAuditEvent, $this> */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(OrderAuditEvent::class);
    }

    /** @return HasMany<OrderReturn, $this> */
    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }
}
