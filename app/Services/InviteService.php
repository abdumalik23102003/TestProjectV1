<?php

namespace App\Services;

use App\Models\User;
use App\Models\TeacherStudent;
use App\Helpers\StringHelper;
use Illuminate\Support\Facades\Hash;

class InviteService
{
    public function generateInviteForTeacher(User $teacher): string
    {
        $token = StringHelper::randomToken(32);
        $tokenHash = hash('sha256', $token);

        // Store hash (not token itself)
        $teacher->update([
            'teacher_approval_token' => $tokenHash,
        ]);

        return $token;
    }

    public function verifyAndJoinStudent(User $student, string $token): bool
    {
        $tokenHash = hash('sha256', $token);

        // Find teacher by token hash
        $teacher = User::where('teacher_approval_token', $tokenHash)
            ->where('status', 'active')
            ->first();

        if (!$teacher) {
            return false;
        }

        // Create or update TeacherStudent relation
        TeacherStudent::updateOrCreate(
            ['teacher_id' => $teacher->id, 'student_id' => $student->id],
            [
                'status' => 'pending',
                'requested_at' => now(),
            ]
        );

        return true;
    }

    public function approveStudent(User $teacher, User $student): bool
    {
        $relation = TeacherStudent::where('teacher_id', $teacher->id)
            ->where('student_id', $student->id)
            ->first();

        if (!$relation) {
            return false;
        }

        $relation->update([
            'status' => 'active',
            'approved_at' => now(),
        ]);

        return true;
    }

    public function rejectStudent(User $teacher, User $student): bool
    {
        $relation = TeacherStudent::where('teacher_id', $teacher->id)
            ->where('student_id', $student->id)
            ->first();

        if (!$relation) {
            return false;
        }

        $relation->update(['status' => 'rejected']);

        return true;
    }

    public function revokeStudent(User $teacher, User $student): bool
    {
        $relation = TeacherStudent::where('teacher_id', $teacher->id)
            ->where('student_id', $student->id)
            ->first();

        if (!$relation) {
            return false;
        }

        $relation->update(['status' => 'revoked']);

        return true;
    }
}
