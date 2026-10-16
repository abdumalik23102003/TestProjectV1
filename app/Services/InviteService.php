<?php

namespace App\Services;

use App\Helpers\StringHelper;
use App\Models\Material;
use App\Models\TeacherStudent;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class InviteService
{
    /**
     * Ustoz uchun yangi taklif tokeni yaratadi (eskisi bekor bo'ladi).
     */
    public function generateInviteForTeacher(User $teacher): string
    {
        $token = StringHelper::randomToken(32);

        $teacher->update(['teacher_approval_token' => $token]);

        return $token;
    }

    /**
     * Token bo'yicha faol ustozni topadi.
     */
    public function findTeacherByToken(string $token): ?User
    {
        $token = trim($token);

        if (! preg_match('/^[A-Za-z0-9]{16,64}$/', $token)) {
            return null;
        }

        return User::where('teacher_approval_token', $token)
            ->where('role', 'teacher')
            ->where('status', 'active')
            ->first();
    }

    /**
     * Faqat kutilayotgan (pending) so'rovni tasdiqlaydi.
     */
    public function approveStudent(User $teacher, User $student): bool
    {
        $relation = $this->findRelation($teacher, $student, 'pending');

        if (! $relation) {
            return false;
        }

        $relation->update([
            'status' => 'active',
            'approved_at' => now(),
        ]);

        return true;
    }

    /**
     * Faqat kutilayotgan (pending) so'rovni rad etadi.
     */
    public function rejectStudent(User $teacher, User $student): bool
    {
        $relation = $this->findRelation($teacher, $student, 'pending');

        if (! $relation) {
            return false;
        }

        $relation->update(['status' => 'rejected']);

        return true;
    }

    /**
     * Faol o'quvchining ruxsatini bekor qiladi.
     */
    public function revokeStudent(User $teacher, User $student): bool
    {
        $relation = $this->findRelation($teacher, $student, 'active');

        if (! $relation) {
            return false;
        }

        $relation->update(['status' => 'revoked']);

        return true;
    }

    public function getStudentRequests(User $teacher, int $limit = 50): Collection
    {
        return TeacherStudent::where('teacher_id', $teacher->id)
            ->where('status', 'pending')
            ->with('student')
            ->orderBy('requested_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getStudentRequestsCount(User $teacher): int
    {
        return TeacherStudent::where('teacher_id', $teacher->id)
            ->where('status', 'pending')
            ->count();
    }

    public function getActiveStudents(User $teacher, int $limit = 50): Collection
    {
        return TeacherStudent::where('teacher_id', $teacher->id)
            ->where('status', 'active')
            ->with('student')
            ->orderBy('approved_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getActiveStudentsCount(User $teacher): int
    {
        return TeacherStudent::where('teacher_id', $teacher->id)
            ->where('status', 'active')
            ->count();
    }

    public function getTeacherConnections(User $student, int $limit = 50): Collection
    {
        return TeacherStudent::where('student_id', $student->id)
            ->where('status', 'active')
            ->with('teacher')
            ->orderBy('approved_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getTeacherConnectionsCount(User $student): int
    {
        return TeacherStudent::where('student_id', $student->id)
            ->where('status', 'active')
            ->count();
    }

    public function hasActiveConnection(User $student, User $teacher): bool
    {
        return $this->findRelation($teacher, $student, 'active') !== null;
    }

    public function getConnectionStatus(User $student, User $teacher): ?string
    {
        return TeacherStudent::where('teacher_id', $teacher->id)
            ->where('student_id', $student->id)
            ->value('status');
    }

    /**
     * Bog'langan ustozlarning oxirgi faol materiallari.
     */
    public function getRecentMaterialsFromTeachers(User $student, int $limit = 20): Collection
    {
        $teacherIds = TeacherStudent::where('student_id', $student->id)
            ->where('status', 'active')
            ->pluck('teacher_id');

        if ($teacherIds->isEmpty()) {
            return new Collection;
        }

        return Material::with('teacher')
            ->whereIn('teacher_id', $teacherIds)
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    private function findRelation(User $teacher, User $student, string $status): ?TeacherStudent
    {
        return TeacherStudent::where('teacher_id', $teacher->id)
            ->where('student_id', $student->id)
            ->where('status', $status)
            ->first();
    }
}
