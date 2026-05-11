<?php

namespace App\Models;

use App\Enums\CourierAssignmentMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingSetting extends Model
{
    protected $fillable = [
        'default_courier_id', 'courier_assignment_default', 'auto_book_on_payment_confirmed',
        'auto_book_cod_orders', 'tracking_sync_interval_minutes', 'sender_snapshot',
        'postex_pickup_address_code', 'postex_store_address_code',
        'default_weight_kg', 'default_length_cm', 'default_width_cm', 'default_height_cm',
    ];

    protected function casts(): array
    {
        return [
            'auto_book_on_payment_confirmed' => 'boolean',
            'auto_book_cod_orders' => 'boolean',
            'sender_snapshot' => 'array',
            'default_weight_kg' => 'decimal:3',
            'default_length_cm' => 'decimal:2',
            'default_width_cm' => 'decimal:2',
            'default_height_cm' => 'decimal:2',
            'courier_assignment_default' => CourierAssignmentMode::class,
        ];
    }

    /** @return BelongsTo<Courier, $this> */
    public function defaultCourier(): BelongsTo
    {
        return $this->belongsTo(Courier::class, 'default_courier_id');
    }

    public static function current(): self
    {
        $row = static::query()->first();
        if ($row !== null) {
            return $row;
        }

        return static::query()->create([
            'courier_assignment_default' => CourierAssignmentMode::Auto,
            'auto_book_on_payment_confirmed' => false,
            'auto_book_cod_orders' => false,
            'tracking_sync_interval_minutes' => 30,
            'sender_snapshot' => [
                'business_name' => config('app.name'),
                'contact_name' => 'Warehouse',
                'phone' => '',
                'city' => 'Karachi',
            ],
            'default_weight_kg' => 1,
            'default_length_cm' => 30,
            'default_width_cm' => 20,
            'default_height_cm' => 15,
        ]);
    }
}
