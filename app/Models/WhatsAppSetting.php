<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppSetting extends Model
{
    protected $table = 'whatsapp_settings';

    protected $fillable = [
        'enabled_customer_notifications',
        'enabled_admin_notifications',
        'admin_recipients',
    ];

    protected function casts(): array
    {
        return [
            'enabled_customer_notifications' => 'boolean',
            'enabled_admin_notifications' => 'boolean',
            'admin_recipients' => 'array',
        ];
    }

    public static function current(): self
    {
        $row = static::query()->first();
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'enabled_customer_notifications' => true,
            'enabled_admin_notifications' => true,
            'admin_recipients' => [],
        ]);
    }
}
