<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContentPost extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'meta_title',
        'meta_description',
        'excerpt',
        'body',
        'pillar_keyword',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** @param  Builder<ContentPost>  $query */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
