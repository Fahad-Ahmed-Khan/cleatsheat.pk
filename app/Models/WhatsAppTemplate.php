<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'key',
        'label',
        'audience',
        'category',
        'body',
        'cloud_template_name',
        'cloud_template_language',
        'has_buttons',
        'button_payloads',
        'is_active',
        'is_system',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'has_buttons' => 'boolean',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'button_payloads' => 'array',
        ];
    }

    public static function findActiveByKey(string $key): ?self
    {
        return static::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->first();
    }
}
