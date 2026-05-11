<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierApiLog extends Model
{
    protected $fillable = [
        'courier_id', 'courier_account_id', 'shipment_id', 'direction', 'endpoint',
        'http_status', 'request_payload', 'response_payload', 'error_message', 'attempt',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
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

    /** @return BelongsTo<Shipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
