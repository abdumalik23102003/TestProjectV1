<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherStudent extends Model
{
    protected $fillable = [
        'teacher_id',
        'student_id',
        'status',
        'requested_at',
        'approved_at'
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime'
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
