<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Courier extends Model
{
    protected $fillable = ['code', 'name', 'config', 'is_active', 'adapter', 'sort_order'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** @return HasMany<Shipment, $this> */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /** @return HasMany<CourierAccount, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(CourierAccount::class);
    }
}
