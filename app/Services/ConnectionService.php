<?php

namespace App\Services;

use App\Models\User;
use App\Models\TeacherStudent;
use App\Helpers\StringHelper;
use Illuminate\Database\Eloquent\Collection;

/**
 * Connection service for teacher-student relationship management
 */
class ConnectionService
{
    protected InviteService $inviteService;
    protected AuditService $auditService;

    public function __construct()
    {
        $this->inviteService = app(InviteService::class);
        $this->auditService = app(AuditService::class);
    }

    /**
     * Send invite link to student (teacher action)
     */
    public function sendInviteToStudent(User $teacher, User $student): ?string
    {
        if (!$teacher->isTeacher() || !$teacher->isActive()) {
            return null;
        }

        // Check if already connected
        $status = $this->inviteService->getConnectionStatus($student, $teacher);
        if ($status === 'active' || $status === 'pending') {
            return null;
        }

        // Generate new invite token
        $token = $this->inviteService->generateInviteForTeacher($teacher);

        // Log the action
        $this->auditService->log(
            'invite_sent',
            'connection',
            $student->id,
            $teacher,
            ['student_name' => $student->full_name]
        );

        return $token;
    }

    /**
     * Get shareable invite link for teacher
     */
    public function getInviteLink(User $teacher): ?string
    {
        if (!$teacher->teacher_approval_token) {
            $token = $this->inviteService->generateInviteForTeacher($teacher);
        } else {
            $token = $teacher->teacher_approval_token;
        }

        $botUsername = config('telegram.bot_username', 'your_bot');
        return "https://t.me/{$botUsername}?start=join_{$token}";
    }

    /**
     * Process student joining via invite link
     */
    public function handleJoinLink(User $student, string $token): bool
    {
        $result = $this->inviteService->verifyAndJoinStudent($student, $token);

        if ($result) {
            // Find the teacher to log
            $tokenHash = hash('sha256', $token);
            $teacher = User::where('teacher_approval_token', $tokenHash)
                ->where('status', 'active')
                ->first();

            if ($teacher) {
                $this->auditService->logConnectionMade(null, $teacher, $student);
            }
        }

        return $result;
    }

    /**
     * Get pending connection requests for teacher
     */
    public function getPendingRequests(User $teacher, int $limit = 50): Collection
    {
        return $this->inviteService->getStudentRequests($teacher, $limit);
    }

    /**
     * Get pending requests count
     */
    public function getPendingRequestsCount(User $teacher): int
    {
        return $this->inviteService->getStudentRequestsCount($teacher);
    }

    /**
     * Get active connections for teacher
     */
    public function getActiveConnections(User $teacher, int $limit = 50): Collection
    {
        return $this->inviteService->getActiveStudents($teacher, $limit);
    }

    /**
     * Get active connections count
     */
    public function getActiveConnectionsCount(User $teacher): int
    {
        return $this->inviteService->getActiveStudentsCount($teacher);
    }

    /**
     * Teacher approves student request
     */
    public function approveStudentRequest(User $teacher, User $student): bool
    {
        $result = $this->inviteService->approveStudent($teacher, $student);

        if ($result) {
            $this->auditService->log(
                'student_approved',
                'connection',
                $student->id,
                $teacher,
                ['student_name' => $student->full_name]
            );
        }

        return $result;
    }

    /**
     * Teacher rejects student request
     */
    public function rejectStudentRequest(User $teacher, User $student): bool
    {
        $result = $this->inviteService->rejectStudent($teacher, $student);

        if ($result) {
            $this->auditService->log(
                'student_rejected',
                'connection',
                $student->id,
                $teacher,
                ['student_name' => $student->full_name]
            );
        }

        return $result;
    }

    /**
     * Teacher removes active student
     */
    public function removeStudentAccess(User $teacher, User $student): bool
    {
        $result = $this->inviteService->revokeStudent($teacher, $student);

        if ($result) {
            $this->auditService->log(
                'student_removed',
                'connection',
                $student->id,
                $teacher,
                ['student_name' => $student->full_name]
            );
        }

        return $result;
    }

    /**
     * Get teacher connections for student
     */
    public function getTeacherConnections(User $student, int $limit = 50): Collection
    {
        return $this->inviteService->getTeacherConnections($student, $limit);
    }

    /**
     * Get teacher connections count
     */
    public function getTeacherConnectionsCount(User $student): int
    {
        return $this->inviteService->getTeacherConnectionsCount($student);
    }

    /**
     * Format connection for display
     */
    public function formatConnectionForDisplay(TeacherStudent $connection): string
    {
        $user = $connection->teacher ?? $connection->student;
        $status = match ($connection->status) {
            'active' => '✅',
            'pending' => '⏳',
            'rejected' => '❌',
            'revoked' => '🚫',
            default => '❓',
        };

        return "{$status} {$user->full_name} ({$user->role})";
    }

    /**
     * Format connections list
     */
    public function formatConnectionsList(Collection $connections): string
    {
        if ($connections->isEmpty()) {
            return __('bot.no_connections');
        }

        $text = '';
        foreach ($connections as $connection) {
            $text .= $this->formatConnectionForDisplay($connection) . "\n";
        }

        return $text;
    }
}
