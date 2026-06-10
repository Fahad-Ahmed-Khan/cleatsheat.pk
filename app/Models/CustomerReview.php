<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerReview extends Model
{
    protected $fillable = [
        'author_name',
        'city',
        'quote',
        'rating',
        'email',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /** @param  Builder<static>  $query */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Shape used by the homepage testimonials carousel.
     *
     * @return array{name: string, city: string, quote: string, rating: int}
     */
    public function toTestimonialPayload(): array
    {
        return [
            'name' => $this->author_name,
            'city' => $this->city ?? '',
            'quote' => $this->quote,
            'rating' => (int) $this->rating,
        ];
    }

    /**
     * @return list<array{name: string, city: string, quote: string, rating: int}>
     */
    public static function testimonialsForHomepage(int $limit = 12): array
    {
        return static::query()
            ->published()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (self $review) => $review->toTestimonialPayload())
            ->all();
    }
}
