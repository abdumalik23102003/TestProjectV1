<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Admin panel management service
 * Provides statistics and management functions for admin dashboard
 */
class AdminPanelService
{
    /**
     * Get all pending teachers
     */
    public function getPendingTeachers(int $limit = 50): Collection
    {
        return User::where('role', 'teacher')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get count of pending teachers
     */
    public function getPendingTeachersCount(): int
    {
        return User::where('role', 'teacher')
            ->where('status', 'pending')
            ->count();
    }

    /**
     * Get all active users
     */
    public function getActiveUsers(int $limit = 50): Collection
    {
        return User::where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get count of active users
     */
    public function getActiveUsersCount(): int
    {
        return User::where('status', 'active')
            ->count();
    }

    /**
     * Get all blocked users
     */
    public function getBlockedUsers(int $limit = 50): Collection
    {
        return User::where('status', 'blocked')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get count of blocked users
     */
    public function getBlockedUsersCount(): int
    {
        return User::where('status', 'blocked')
            ->count();
    }

    /**
     * Get users by role
     */
    public function getUsersByRole(string $role, int $limit = 50): Collection
    {
        return User::where('role', $role)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get count of users by role
     */
    public function getUsersCountByRole(string $role): int
    {
        return User::where('role', $role)
            ->count();
    }

    /**
     * Get user statistics
     */
    public function getUserStats(): array
    {
        return [
            'total' => User::count(),
            'active' => $this->getActiveUsersCount(),
            'pending' => User::where('status', 'pending')->count(),
            'blocked' => $this->getBlockedUsersCount(),
            'teachers' => $this->getUsersCountByRole('teacher'),
            'students' => $this->getUsersCountByRole('student'),
            'admins' => $this->getUsersCountByRole('admin'),
        ];
    }

    /**
     * Block user
     */
    public function blockUser(User $user, string $reason = null): User
    {
        $user->status = 'blocked';
        $user->save();

        return $user;
    }

    /**
     * Unblock user
     */
    public function unblockUser(User $user): User
    {
        $user->status = 'active';
        $user->save();

        return $user;
    }

    /**
     * Approve teacher
     */
    public function approveTeacher(User $teacher): User
    {
        $teacher->status = 'active';
        $teacher->teacher_approved_at = now();
        $teacher->save();

        return $teacher;
    }

    /**
     * Reject teacher
     */
    public function rejectTeacher(User $teacher, string $reason = null): User
    {
        $teacher->status = 'rejected';
        $teacher->save();

        return $teacher;
    }

    /**
     * Get user by ID
     */
    public function getUserById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Get user by telegram ID
     */
    public function getUserByTelegramId(int $telegramUserId): ?User
    {
        return User::where('telegram_user_id', $telegramUserId)->first();
    }

    /**
     * Search users by name
     */
    public function searchUsers(string $query, int $limit = 50): Collection
    {
        return User::where('full_name', 'like', "%{$query}%")
            ->orderBy('full_name')
            ->limit($limit)
            ->get();
    }

    /**
     * Format user for display
     */
    public function formatUserForDisplay(User $user): string
    {
        $role = $user->role;
        $status = $user->status;
        $icon = match ($status) {
            'active' => '✅',
            'pending' => '⏳',
            'rejected' => '❌',
            'blocked' => '🚫',
            default => '❓',
        };

        return "{$icon} {$user->full_name} ({$role}) - ID: {$user->id}";
    }

    /**
     * Format users list for display
     */
    public function formatUsersList(Collection $users): string
    {
        if ($users->isEmpty()) {
            return __('bot.no_results');
        }

        $text = '';
        foreach ($users as $user) {
            $text .= $this->formatUserForDisplay($user) . "\n";
        }

        return $text;
    }

    /**
     * Get system statistics
     */
    public function getSystemStats(): array
    {
        $auditService = app(AuditService::class);

        return [
            'users' => $this->getUserStats(),
            'audit_actions_7days' => $auditService->getStats(7),
            'total_materials' => \App\Models\Material::count(),
            'total_audit_logs' => \App\Models\AuditLog::count(),
        ];
    }
}
