<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingSetting extends Model
{
    protected $table = 'marketing_settings';

    protected $fillable = [
        'home_meta_title',
        'home_meta_description',
        'default_og_image_url',
        'twitter_site',
        'ga4_enabled',
        'ga4_measurement_id',
        'meta_pixel_enabled',
        'meta_pixel_id',
        'tiktok_pixel_enabled',
        'tiktok_pixel_id',
        'robots_mode',
        'robots_custom',
    ];

    protected function casts(): array
    {
        return [
            'ga4_enabled' => 'boolean',
            'meta_pixel_enabled' => 'boolean',
            'tiktok_pixel_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        $row = static::query()->first();
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'robots_mode' => 'allow_all',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicPayload(): array
    {
        return [
            'ga4_enabled' => (bool) $this->ga4_enabled && filled($this->ga4_measurement_id),
            'ga4_measurement_id' => $this->ga4_measurement_id,
            'meta_pixel_enabled' => (bool) $this->meta_pixel_enabled && filled($this->meta_pixel_id),
            'meta_pixel_id' => $this->meta_pixel_id,
            'tiktok_pixel_enabled' => (bool) $this->tiktok_pixel_enabled && filled($this->tiktok_pixel_id),
            'tiktok_pixel_id' => $this->tiktok_pixel_id,
        ];
    }
}
