<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_user_id',
        'full_name',
        'role',
        'status',
        'teacher_approval_token',
        'teacher_approved_at',
    ];

    protected $casts = [
        'teacher_approved_at' => 'datetime',
    ];

    public function materials()
    {
        return $this->hasMany(Material::class, 'teacher_id');
    }

    public function teacherConnections()
    {
        return $this->belongsToMany(User::class, 'teacher_students', 'student_id', 'teacher_id')
            ->withPivot('status', 'requested_at', 'approved_at');
    }

    public function studentConnections()
    {
        return $this->belongsToMany(User::class, 'teacher_students', 'teacher_id', 'student_id')
            ->withPivot('status', 'requested_at', 'approved_at');
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
