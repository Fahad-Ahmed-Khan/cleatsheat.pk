<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariantSize extends Model
{
    protected $fillable = [
        'product_variant_id', 'size_label', 'uk_size', 'eu_size', 'pk_size', 'stock_qty', 'low_stock_threshold',
    ];

    protected function casts(): array
    {
        return [
            'stock_qty' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
