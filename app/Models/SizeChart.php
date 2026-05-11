<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\ShoeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SizeChart extends Model
{
    protected $fillable = ['brand_id', 'name', 'gender', 'shoe_type'];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'shoe_type' => ShoeType::class,
        ];
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return HasMany<SizeChartRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(SizeChartRow::class)->orderBy('sort_order');
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
