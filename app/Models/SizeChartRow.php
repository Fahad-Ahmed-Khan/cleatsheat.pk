<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SizeChartRow extends Model
{
    protected $fillable = [
        'size_chart_id', 'sort_order', 'label', 'uk_size', 'eu_size', 'pk_size', 'foot_cm', 'measurements',
    ];

    protected function casts(): array
    {
        return [
            'measurements' => 'array',
            'foot_cm' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<SizeChart, $this> */
    public function sizeChart(): BelongsTo
    {
        return $this->belongsTo(SizeChart::class);
    }
}
