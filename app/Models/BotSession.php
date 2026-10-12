<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotSession extends Model
{
    protected $fillable = [
        'telegram_user_id',
        'process_type',
        'current_step',
        'payload',
        'expires_at'
    ];

    protected $casts = [
        'payload' => 'json',
        'expires_at' => 'datetime'
    ];
}
