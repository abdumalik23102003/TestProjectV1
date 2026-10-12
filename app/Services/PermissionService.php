<?php

namespace App\Services;

use App\Models\User;
use App\Models\Material;
use App\Models\TeacherStudent;

class PermissionService
{
    public function canViewMaterial(User $user, Material $material): bool
    {
        // Fayl egasi
        if ($material->teacher_id === $user->id) {
            return true;
        }

        // Public material
        if ($material->isPublic()) {
            return true;
        }

        // Private material — bog'lanish kerak
        if ($material->visibility === 'private') {
            return TeacherStudent::where('teacher_id', $material->teacher_id)
                ->where('student_id', $user->id)
                ->where('status', 'active')
                ->exists();
        }

        return false;
    }

    public function canEditMaterial(User $user, Material $material): bool
    {
        return $material->teacher_id === $user->id;
    }

    public function canDeleteMaterial(User $user, Material $material): bool
    {
        return $material->teacher_id === $user->id;
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
