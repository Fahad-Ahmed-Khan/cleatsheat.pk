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
        'highest_customer_offer_seen',
        'lowest_shop_offer_given',
        'customer_integrity_floor',
        'concession_count',
        'negotiation_turn_count',
        'resistance_score',
        'stubborn_customer_mode',
        'last_shop_concession_at',
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
            'highest_customer_offer_seen' => 'decimal:2',
            'lowest_shop_offer_given' => 'decimal:2',
            'customer_integrity_floor' => 'decimal:2',
            'concession_count' => 'integer',
            'negotiation_turn_count' => 'integer',
            'resistance_score' => 'integer',
            'stubborn_customer_mode' => 'boolean',
            'last_shop_concession_at' => 'datetime',
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
