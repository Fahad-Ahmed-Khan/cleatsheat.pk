<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = ['channel', 'recipient', 'template_key', 'payload', 'status', 'error_message'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
