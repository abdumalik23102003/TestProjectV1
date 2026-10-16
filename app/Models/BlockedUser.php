<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedUser extends Model
{
    protected $fillable = [
        'user_id',
        'blocked_by_admin_id',
        'reason',
        'blocked_at',
        'unblock_at',
    ];

    protected $casts = [
        'blocked_at' => 'datetime',
        'unblock_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'blocked_by_admin_id');
    }
}
