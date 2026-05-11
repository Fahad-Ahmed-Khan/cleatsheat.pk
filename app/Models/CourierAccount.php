<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourierAccount extends Model
{
    protected $fillable = [
        'courier_id', 'name', 'credentials', 'service_code', 'cod_allowed',
        'city_restrictions', 'is_active', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'city_restrictions' => 'array',
            'cod_allowed' => 'boolean',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<Courier, $this> */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    /** @return HasMany<Shipment, $this> */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
