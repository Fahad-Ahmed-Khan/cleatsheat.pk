<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    protected $fillable = [
        'order_id', 'courier_id', 'courier_account_id', 'tracking_number', 'booking_reference',
        'status', 'meta', 'cod_amount', 'shipping_charges',
        'weight_kg', 'length_cm', 'width_cm', 'height_cm',
        'sender_snapshot', 'receiver_snapshot',
        'label_url', 'invoice_url',
        'last_booking_response', 'last_tracking_response',
        'shipped_at', 'booked_at', 'delivered_at', 'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'meta' => 'array',
            'sender_snapshot' => 'array',
            'receiver_snapshot' => 'array',
            'last_booking_response' => 'array',
            'last_tracking_response' => 'array',
            'cod_amount' => 'decimal:2',
            'shipping_charges' => 'decimal:2',
            'weight_kg' => 'decimal:3',
            'length_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'shipped_at' => 'datetime',
            'booked_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Courier, $this> */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    /** @return BelongsTo<CourierAccount, $this> */
    public function courierAccount(): BelongsTo
    {
        return $this->belongsTo(CourierAccount::class);
    }

    /** @return HasMany<ShipmentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }
}
