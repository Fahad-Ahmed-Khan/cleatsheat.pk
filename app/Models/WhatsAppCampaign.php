<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppCampaign extends Model
{
    protected $table = 'whatsapp_campaigns';

    protected $fillable = [

        'name',

        'template_id',

        'segment',

        'status',

        'scheduled_for',

        'sent_count',

        'failed_count',

        'created_by',

    ];

    protected function casts(): array
    {

        return [

            'segment' => 'array',

            'scheduled_for' => 'datetime',

            'sent_count' => 'integer',

            'failed_count' => 'integer',

        ];

    }

    /** @return BelongsTo<WhatsAppTemplate, $this> */
    public function template(): BelongsTo
    {

        return $this->belongsTo(WhatsAppTemplate::class, 'template_id');

    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {

        return $this->belongsTo(User::class, 'created_by');

    }

    /** @return HasMany<WhatsAppCampaignRecipient, $this> */
    public function recipients(): HasMany
    {

        return $this->hasMany(WhatsAppCampaignRecipient::class, 'campaign_id');

    }

}
