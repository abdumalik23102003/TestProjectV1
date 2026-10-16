<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\LikeEscape;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Lang;

/**
 * Audit logging service
 * Logs all important actions for compliance and debugging
 */
class AuditService
{
    /**
     * Log generic action
     */
    public function log(
        string $actionType,
        string $subjectType,
        ?int $subjectId = null,
        ?User $actor = null,
        ?array $metadata = null
    ): AuditLog {
        return AuditLog::create([
            'actor_id' => $actor?->id,
            'action_type' => $actionType,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * User registered
     */
    public function logRegistration(User $user, string $role): AuditLog
    {
        return $this->log(
            'user_registered',
            'user',
            $user->id,
            $user,
            ['role' => $role]
        );
    }

    /**
     * User role changed
     */
    public function logRoleChange(User $actor, User $user, string $oldRole, string $newRole): AuditLog
    {
        return $this->log(
            'user_role_changed',
            'user',
            $user->id,
            $actor,
            ['old_role' => $oldRole, 'new_role' => $newRole]
        );
    }

    /**
     * Teacher approved
     */
    public function logTeacherApproved(User $admin, User $teacher): AuditLog
    {
        return $this->log(
            'teacher_approved',
            'user',
            $teacher->id,
            $admin,
            ['teacher_name' => $teacher->full_name]
        );
    }

    /**
     * Teacher rejected
     */
    public function logTeacherRejected(User $admin, User $teacher, ?string $reason = null): AuditLog
    {
        return $this->log(
            'teacher_rejected',
            'user',
            $teacher->id,
            $admin,
            ['teacher_name' => $teacher->full_name, 'reason' => $reason]
        );
    }

    /**
     * User blocked
     */
    public function logUserBlocked(User $admin, User $user, ?string $reason = null): AuditLog
    {
        return $this->log(
            'user_blocked',
            'user',
            $user->id,
            $admin,
            ['reason' => $reason]
        );
    }

    /**
     * User unblocked
     */
    public function logUserUnblocked(User $admin, User $user): AuditLog
    {
        return $this->log(
            'user_unblocked',
            'user',
            $user->id,
            $admin
        );
    }

    /**
     * File uploaded
     */
    public function logFileUploaded(User $teacher, int $materialId, string $filename): AuditLog
    {
        return $this->log(
            'material_uploaded',
            'material',
            $materialId,
            $teacher,
            ['filename' => $filename]
        );
    }

    /**
     * File deleted
     */
    public function logFileDeleted(User $teacher, int $materialId, string $filename): AuditLog
    {
        return $this->log(
            'material_deleted',
            'material',
            $materialId,
            $teacher,
            ['filename' => $filename]
        );
    }

    /**
     * File downloaded
     */
    public function logFileDownloaded(User $student, int $materialId, string $filename): AuditLog
    {
        return $this->log(
            'material_downloaded',
            'material',
            $materialId,
            $student,
            ['filename' => $filename]
        );
    }

    /**
     * Material searched
     */
    public function logMaterialSearch(User $user, string $query, int $resultCount): AuditLog
    {
        return $this->log(
            'material_searched',
            'search',
            null,
            $user,
            ['query' => $query, 'result_count' => $resultCount]
        );
    }

    /**
     * Teacher-student connection made
     */
    public function logConnectionMade(?User $actor, User $teacher, User $student): AuditLog
    {
        return $this->log(
            'teacher_student_connected',
            'connection',
            null,
            $actor,
            ['teacher_id' => $teacher->id, 'student_id' => $student->id]
        );
    }

    /**
     * Teacher-student connection removed
     */
    public function logConnectionRemoved(User $actor, User $teacher, User $student): AuditLog
    {
        return $this->log(
            'teacher_student_disconnected',
            'connection',
            null,
            $actor,
            ['teacher_id' => $teacher->id, 'student_id' => $student->id]
        );
    }

    /**
     * Query logs by actor
     */
    public function getByActor(User $actor, int $limit = 50): Collection
    {
        return AuditLog::where('actor_id', $actor->id)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Query logs by action type
     */
    public function getByAction(string $actionType, int $limit = 50): Collection
    {
        return AuditLog::where('action_type', $actionType)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Query logs by subject
     */
    public function getBySubject(string $subjectType, int $subjectId, int $limit = 50): Collection
    {
        return AuditLog::where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Query logs in date range
     */
    public function getByDateRange(\DateTime $from, \DateTime $to, int $limit = 50): Collection
    {
        return AuditLog::whereBetween('created_at', [$from, $to])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get latest logs
     */
    public function getLatest(int $limit = 50): Collection
    {
        return AuditLog::latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get logs for admin dashboard (last 10 with details)
     */
    public function getDashboardLogs(int $count = 10): array
    {
        $logs = AuditLog::with('actor')
            ->latest()
            ->limit($count)
            ->get();

        return $logs->map(function (AuditLog $log) {
            return [
                'action' => __("bot.audit_action_{$log->action_type}"),
                'actor' => $log->actor?->full_name ?? 'System',
                'subject' => __("bot.audit_subject_{$log->subject_type}"),
                'time' => $log->created_at->format('H:i'),
                'date' => $log->created_at->format('d.m.Y'),
            ];
        })->toArray();
    }

    /**
     * Format audit log for display
     */
    public function formatForDisplay(AuditLog $log): string
    {
        $key = "bot.audit_action_{$log->action_type}";
        $action = Lang::has($key) ? __($key) : e($log->action_type);
        $actor = e($log->actor?->full_name ?? 'System');
        $time = $log->created_at->format('d.m.Y H:i');

        return "{$time} — {$actor}: {$action}";
    }

    /**
     * Search logs by actor name or action
     */
    public function search(string $query, int $limit = 50): Collection
    {
        return AuditLog::whereHas('actor', function ($q) use ($query) {
            $q->where('full_name', 'like', LikeEscape::contains($query));
        })
            ->orWhere('action_type', 'like', LikeEscape::contains($query))
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get action statistics
     */
    public function getStats(int $days = 7): array
    {
        $from = now()->subDays($days);

        $stats = AuditLog::where('created_at', '>=', $from)
            ->groupBy('action_type')
            ->selectRaw('action_type, COUNT(*) as count')
            ->get()
            ->pluck('count', 'action_type')
            ->toArray();

        return $stats;
    }

    /**
     * Clean old logs (older than X days)
     */
    public function cleanOldLogs(int $days = 90): int
    {
        $before = now()->subDays($days);

        return AuditLog::where('created_at', '<', $before)->delete();
    }
}
