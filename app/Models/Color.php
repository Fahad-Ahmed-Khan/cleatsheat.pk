<?php

namespace App\Models;

use Database\Factories\ColorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Color extends Model
{
    /** @use HasFactory<ColorFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'hex'];

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
