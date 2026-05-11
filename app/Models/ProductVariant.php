<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'color_id', 'sku', 'price', 'compare_at_price',
        'bargain_enabled', 'bargain_min_price', 'bargain_max_discount_percent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'bargain_enabled' => 'boolean',
            'bargain_min_price' => 'decimal:2',
            'bargain_max_discount_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Color, $this> */
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    /** @return HasMany<VariantSize, $this> */
    public function sizes(): HasMany
    {
        return $this->hasMany(VariantSize::class);
    }
}
