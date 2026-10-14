<?php

namespace App\Services;

use App\Models\User;
use App\Models\TeacherStudent;
use App\Helpers\StringHelper;
use Illuminate\Database\Eloquent\Collection;

class InviteService
{
    /**
     * Generate invite token for teacher
     */
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

    /**
     * Verify token and create pending student connection
     */
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

    /**
     * Approve student connection
     */
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

    /**
     * Reject student connection
     */
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

    /**
     * Revoke student access
     */
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

    /**
     * Get pending student requests for teacher
     */
    public function getStudentRequests(User $teacher, int $limit = 50): Collection
    {
        return TeacherStudent::where('teacher_id', $teacher->id)
            ->where('status', 'pending')
            ->with('student')
            ->orderBy('requested_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get pending student requests count
     */
    public function getStudentRequestsCount(User $teacher): int
    {
        return TeacherStudent::where('teacher_id', $teacher->id)
            ->where('status', 'pending')
            ->count();
    }

    /**
     * Get active students for teacher
     */
    public function getActiveStudents(User $teacher, int $limit = 50): Collection
    {
        return TeacherStudent::where('teacher_id', $teacher->id)
            ->where('status', 'active')
            ->with('student')
            ->orderBy('approved_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get active students count
     */
    public function getActiveStudentsCount(User $teacher): int
    {
        return TeacherStudent::where('teacher_id', $teacher->id)
            ->where('status', 'active')
            ->count();
    }

    /**
     * Get teacher connections for student
     */
    public function getTeacherConnections(User $student, int $limit = 50): Collection
    {
        return TeacherStudent::where('student_id', $student->id)
            ->where('status', 'active')
            ->with('teacher')
            ->orderBy('approved_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get teacher connections count
     */
    public function getTeacherConnectionsCount(User $student): int
    {
        return TeacherStudent::where('student_id', $student->id)
            ->where('status', 'active')
            ->count();
    }

    /**
     * Check if student has active connection to teacher
     */
    public function hasActiveConnection(User $student, User $teacher): bool
    {
        return TeacherStudent::where('teacher_id', $teacher->id)
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Get connection status
     */
    public function getConnectionStatus(User $student, User $teacher): ?string
    {
        $relation = TeacherStudent::where('teacher_id', $teacher->id)
            ->where('student_id', $student->id)
            ->first();

        return $relation?->status;
    }

    /**
     * Get all teacher connections (any status)
     */
    public function getAllConnections(User $teacher, int $limit = 50): Collection
    {
        return TeacherStudent::where('teacher_id', $teacher->id)
            ->with('student')
            ->orderBy('requested_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent materials from connected teachers
     */
    public function getRecentMaterialsFromTeachers(User $student, int $limit = 20): Collection
    {
        $connections = TeacherStudent::where('student_id', $student->id)
            ->where('status', 'active')
            ->pluck('teacher_id');

        if ($connections->isEmpty()) {
            return collect();
        }

        return \App\Models\Material::whereIn('teacher_id', $connections)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
