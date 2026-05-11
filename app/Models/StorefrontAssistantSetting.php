<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontAssistantSetting extends Model
{
    protected $table = 'storefront_assistant_settings';

    protected $fillable = [
        'enabled',
        'delay_seconds',
        'snooze_days',
        'allowed_routes_json',
        'ui_json',
        'steps_json',
        'mapping_json',
        'preview_enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'delay_seconds' => 'integer',
            'snooze_days' => 'integer',
            'allowed_routes_json' => 'array',
            'ui_json' => 'array',
            'steps_json' => 'array',
            'mapping_json' => 'array',
            'preview_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        $row = static::query()->first();
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'enabled' => true,
            'delay_seconds' => 4,
            'snooze_days' => 7,
            'allowed_routes_json' => [],
            'ui_json' => [],
            'steps_json' => [],
            'mapping_json' => [],
            'preview_enabled' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicPayload(): array
    {
        return [
            'enabled' => (bool) $this->enabled,
            'delay_seconds' => max(0, (int) $this->delay_seconds),
            'snooze_days' => max(0, (int) $this->snooze_days),
            'allowed_routes' => array_values(array_filter(array_map(
                static fn (mixed $v): ?string => is_string($v) ? trim($v) : null,
                (array) ($this->allowed_routes_json ?? [])
            ))),
            'ui' => is_array($this->ui_json) ? $this->ui_json : [],
            'steps' => is_array($this->steps_json) ? $this->steps_json : [],
            'mapping' => is_array($this->mapping_json) ? $this->mapping_json : [],
            'preview_enabled' => (bool) $this->preview_enabled,
        ];
    }
}

