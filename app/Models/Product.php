<?php

namespace App\Models;

use App\Enums\FitGuidance;
use App\Enums\Gender;
use App\Enums\ShoeType;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'brand_id', 'category_id', 'size_chart_id', 'name', 'slug', 'description',
        'meta_title', 'meta_description', 'canonical_url', 'video_url', 'video_poster',
        'fit_guidance', 'gender', 'shoe_type', 'fit_notes', 'size_info', 'features', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fit_guidance' => FitGuidance::class,
            'gender' => Gender::class,
            'shoe_type' => ShoeType::class,
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<SizeChart, $this> */
    public function sizeChart(): BelongsTo
    {
        return $this->belongsTo(SizeChart::class);
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return HasMany<ProductReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)->latest();
    }

    public function primaryImage(): ?ProductImage
    {
        return $this->images()->first();
    }
}
