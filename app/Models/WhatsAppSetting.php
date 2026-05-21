<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppSetting extends Model
{
    protected $table = 'whatsapp_settings';

    protected $fillable = [
        'enabled_customer_notifications',
        'enabled_admin_notifications',
        'enabled_cod_confirmation',
        'enabled_shipment_status_customer_alerts',
        'enabled_pickup_notices',
        'pickup_notice_time',
        'cloud_webhook_verify_token',
        'marketing_opt_out_keyword',
        'promotional_throttle_per_minute',
        'admin_recipients',
    ];

    protected function casts(): array
    {
        return [
            'enabled_customer_notifications' => 'boolean',
            'enabled_admin_notifications' => 'boolean',
            'enabled_cod_confirmation' => 'boolean',
            'enabled_shipment_status_customer_alerts' => 'boolean',
            'enabled_pickup_notices' => 'boolean',
            'promotional_throttle_per_minute' => 'integer',
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
            'enabled_cod_confirmation' => true,
            'enabled_shipment_status_customer_alerts' => true,
            'enabled_pickup_notices' => true,
            'pickup_notice_time' => '11:00',
            'marketing_opt_out_keyword' => 'STOP',
            'promotional_throttle_per_minute' => 20,
            'admin_recipients' => [],
        ]);
    }
}
