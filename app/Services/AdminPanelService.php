<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Material;
use App\Models\User;
use App\Support\LikeEscape;
use Illuminate\Database\Eloquent\Collection;

/**
 * Admin paneli uchun foydalanuvchilarni boshqarish va statistika.
 */
class AdminPanelService
{
    public function getPendingTeachers(int $limit = 50): Collection
    {
        return User::where('role', 'teacher')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getPendingTeachersCount(): int
    {
        return User::where('role', 'teacher')->where('status', 'pending')->count();
    }

    public function getActiveUsers(int $limit = 50): Collection
    {
        return User::where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getActiveUsersCount(): int
    {
        return User::where('status', 'active')->count();
    }

    public function getBlockedUsers(int $limit = 50): Collection
    {
        return User::where('status', 'blocked')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getBlockedUsersCount(): int
    {
        return User::where('status', 'blocked')->count();
    }

    public function getUsersByRole(string $role, int $limit = 50): Collection
    {
        return User::where('role', $role)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getUsersCountByRole(string $role): int
    {
        return User::where('role', $role)->count();
    }

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
     * Admin $actor foydalanuvchi $target ni bloklay olmasa, sababning til kalitini qaytaradi.
     */
    public function cannotBlockReason(User $actor, User $target): ?string
    {
        if ($actor->id === $target->id) {
            return 'bot.admin_cannot_block_self';
        }

        if ($target->role === 'admin') {
            return 'bot.admin_cannot_block_admin';
        }

        if ($target->status === 'blocked') {
            return 'bot.user_already_blocked';
        }

        return null;
    }

    /**
     * Foydalanuvchini bloklaydi va uning ochiq wizard sessiyalarini tozalaydi.
     */
    public function blockUser(User $user, ?string $reason = null): User
    {
        $user->status = 'blocked';
        $user->save();

        SessionService::clearSession($user->telegram_user_id);

        return $user;
    }

    /**
     * Blokdan chiqaradi. Hech qachon tasdiqlanmagan ustoz "pending" holatiga qaytadi
     * (blok orqali tasdiqlashni aylanib o'tib bo'lmasligi uchun), qolganlar "active".
     */
    public function unblockUser(User $user): User
    {
        $needsApproval = $user->role === 'teacher' && $user->teacher_approved_at === null;

        $user->status = $needsApproval ? 'pending' : 'active';
        $user->save();

        return $user;
    }

    public function approveTeacher(User $teacher): User
    {
        $teacher->status = 'active';
        $teacher->teacher_approved_at = now();
        $teacher->save();

        return $teacher;
    }

    public function rejectTeacher(User $teacher, ?string $reason = null): User
    {
        $teacher->status = 'rejected';
        $teacher->save();

        return $teacher;
    }

    public function getUserById(int $id): ?User
    {
        return User::find($id);
    }

    public function getUserByTelegramId(int $telegramUserId): ?User
    {
        return User::where('telegram_user_id', $telegramUserId)->first();
    }

    public function searchUsers(string $query, int $limit = 50): Collection
    {
        return User::where('full_name', 'like', LikeEscape::contains($query))
            ->orderBy('full_name')
            ->limit($limit)
            ->get();
    }

    public function formatUserForDisplay(User $user): string
    {
        $icon = match ($user->status) {
            'active' => '✅',
            'pending' => '⏳',
            'rejected' => '❌',
            'blocked' => '🚫',
            default => '❓',
        };

        return "{$icon} ".e($user->full_name)." ({$user->role}) - ID: {$user->id}";
    }

    public function formatUsersList(Collection $users): string
    {
        if ($users->isEmpty()) {
            return __('bot.no_results');
        }

        $text = '';

        foreach ($users as $user) {
            $text .= $this->formatUserForDisplay($user)."\n";
        }

        return $text;
    }

    public function getSystemStats(): array
    {
        return [
            'users' => $this->getUserStats(),
            'audit_actions_7days' => app(AuditService::class)->getStats(7),
            'total_materials' => Material::count(),
            'total_audit_logs' => AuditLog::count(),
        ];
    }
}
