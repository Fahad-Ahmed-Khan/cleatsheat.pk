<?php

namespace App\Models;

use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'meta_title', 'meta_description', 'logo_path'];

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<SizeChart, $this> */
    public function sizeCharts(): HasMany
    {
        return $this->hasMany(SizeChart::class);
    }
}
