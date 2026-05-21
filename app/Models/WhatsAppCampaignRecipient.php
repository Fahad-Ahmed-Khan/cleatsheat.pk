<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppCampaignRecipient extends Model
{
    protected $table = 'whatsapp_campaign_recipients';

    protected $fillable = [

        'campaign_id',

        'user_id',

        'phone',

        'name',

        'status',

        'error',

        'sent_at',

    ];

    protected function casts(): array
    {

        return [

            'sent_at' => 'datetime',

        ];

    }

    /** @return BelongsTo<WhatsAppCampaign, $this> */
    public function campaign(): BelongsTo
    {

        return $this->belongsTo(WhatsAppCampaign::class, 'campaign_id');

    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {

        return $this->belongsTo(User::class);

    }

}
