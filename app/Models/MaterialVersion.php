<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialVersion extends Model
{
    protected $fillable = [
        'material_id',
        'version_number',
        'storage_path',
        'size_bytes',
        'checksum',
        'uploaded_at',
        'status',
        'retain_until',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'retain_until' => 'datetime',
    ];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}
