<?php

namespace App\Services;

use App\Models\Material;
use App\Models\TeacherStudent;
use App\Models\User;

class PermissionService
{
    public function canViewMaterial(User $user, Material $material): bool
    {
        // Faqat faol materiallarni ko'rish mumkin.
        if ($material->status !== 'active') {
            return false;
        }

        // Fayl egasi.
        if ((int) $material->teacher_id === (int) $user->id) {
            return true;
        }

        // Ommaviy material.
        if ($material->isPublic()) {
            return true;
        }

        // Shaxsiy material: ustoz bilan faol bog'lanish talab qilinadi.
        return TeacherStudent::where('teacher_id', $material->teacher_id)
            ->where('student_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    public function canEditMaterial(User $user, Material $material): bool
    {
        return (int) $material->teacher_id === (int) $user->id;
    }

    public function canDeleteMaterial(User $user, Material $material): bool
    {
        return (int) $material->teacher_id === (int) $user->id;
    }

    public function canUpload(User $user): bool
    {
        return $user->isTeacher() && $user->isActive();
    }

    public function canApproveTeacher(User $user): bool
    {
        return $user->isAdmin();
    }
}
