<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickupDispatch extends Model
{
    protected $table = 'pickup_dispatches';

    protected $fillable = [
        'courier_id',
        'rider_id',
        'dispatch_date',
        'parcel_count',
        'cod_total',
        'shipment_ids',
        'tracking_numbers',
        'sent_via',
        'sent_at',
        'notification_log_id',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'dispatch_date' => 'date',
            'sent_at' => 'datetime',
            'shipment_ids' => 'array',
            'tracking_numbers' => 'array',
            'cod_total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Courier, $this> */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    /** @return BelongsTo<CourierRider, $this> */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(CourierRider::class, 'rider_id');
    }
}
