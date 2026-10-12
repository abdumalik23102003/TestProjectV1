<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'action_type',
        'subject_type',
        'subject_id',
        'metadata',
        'created_at'
    ];

    protected $casts = [
        'metadata' => 'json',
        'created_at' => 'datetime'
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
