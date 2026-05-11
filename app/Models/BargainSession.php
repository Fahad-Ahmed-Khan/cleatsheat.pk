<?php

namespace App\Models;

use App\Enums\BargainSessionState;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class BargainSession extends Model
{
    protected $fillable = [
        'user_id',
        'guest_token',
        'customer_phone',
        'customer_name',
        'customer_key',
        'product_variant_id',
        'state',
        'list_price',
        'current_offer',
        'accepted_price',
        'checkout_token',
        'lock_consumed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => BargainSessionState::class,
            'list_price' => 'decimal:2',
            'current_offer' => 'decimal:2',
            'accepted_price' => 'decimal:2',
            'expires_at' => 'datetime',
            'lock_consumed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return HasMany<BargainMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(BargainMessage::class)->orderBy('id');
    }
}
