<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourierRider extends Model
{
    protected $table = 'couriers_riders';

    protected $fillable = [
        'courier_id',
        'name',
        'phone',
        'alt_phone',
        'is_active',
        'is_primary',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_primary' => 'boolean',
        ];
    }

    /** @return BelongsTo<Courier, $this> */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    /** @return HasMany<PickupDispatch, $this> */
    public function dispatches(): HasMany
    {
        return $this->hasMany(PickupDispatch::class, 'rider_id');
    }
}
