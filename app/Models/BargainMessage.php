<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class BargainMessage extends Model
{
    protected $fillable = [
        'bargain_session_id',
        'role',
        'body',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<BargainSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(BargainSession::class, 'bargain_session_id');
    }
}
