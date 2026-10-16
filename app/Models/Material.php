<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $fillable = [
        'teacher_id',
        'original_filename',
        'title',
        'description',
        'category_id',
        'visibility',
        'status',
        'storage_disk',
        'storage_path',
        'size_bytes',
        'mime_type',
        'checksum',
        'current_version_id',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function versions()
    {
        return $this->hasMany(MaterialVersion::class)->orderBy('version_number', 'desc');
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
