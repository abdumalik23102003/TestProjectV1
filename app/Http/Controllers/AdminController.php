<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Material;
use App\Models\AuditLog;
use App\Models\BlockedUser;
use App\Services\TelegramService;
use App\Services\PermissionService;
use App\Services\StorageService;

class AdminController extends Controller
{
    protected TelegramService $telegramService;
    protected StorageService $storageService;

    public function __construct(TelegramService $telegramService, StorageService $storageService)
    {
        $this->telegramService = $telegramService;
        $this->storageService = $storageService;
    }

    public function approvePendingTeachers(User $admin): void
    {
        if (!$admin->isAdmin()) {
            $this->telegramService->sendMessage($admin->telegram_user_id, "❌ Admin emas.");
            return;
        }

        $teachers = User::where('role', 'teacher')
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($teachers->isEmpty()) {
            $this->telegramService->sendMessage($admin->telegram_user_id, "✅ Tasdiqga kutayotgan ustoz yo'q.");
            return;
        }

        $text = "⏳ Tasdiqqa kutayotgan ustozlar:\n\n";
        $buttons = [];

        foreach ($teachers as $teacher) {
            $text .= "👨‍🏫 " . $teacher->full_name . "\n";
            $buttons[] = [
                'text' => "✅ " . \Illuminate\Support\Str::limit($teacher->full_name, 15),
                'callback_data' => "approve_teacher:{$teacher->id}"
            ];
        }

        $this->telegramService->sendMessage($admin->telegram_user_id, $text, $buttons);
    }

    public function approveTeacher(User $admin, int $teacherId): void
    {
        if (!$admin->isAdmin()) {
            $this->telegramService->sendMessage($admin->telegram_user_id, "❌ Admin emas.");
            return;
        }

        $teacher = User::findOrFail($teacherId);

        $teacher->update(['status' => 'active']);

        AuditLog::create([
            'actor_id' => $admin->id,
            'action_type' => 'teacher_approved',
            'subject_type' => 'user',
            'subject_id' => $teacher->id,
            'created_at' => now()
        ]);

        // Ustoza bildirim
        $joinLink = route('telegram.join', ['token' => $teacher->teacher_approval_token]);
        $this->telegramService->sendMessage(
            $teacher->telegram_user_id,
            "✅ Tasdiqlandingiz! Taklif havolasi:\n" . $joinLink
        );

        $this->telegramService->sendMessage($admin->telegram_user_id, "✅ Ustoz tasdiqlandi.");
    }

    public function rejectTeacher(User $admin, int $teacherId): void
    {
        if (!$admin->isAdmin()) {
            return;
        }

        $teacher = User::findOrFail($teacherId);
        $teacher->update(['status' => 'rejected']);

        AuditLog::create([
            'actor_id' => $admin->id,
            'action_type' => 'teacher_rejected',
            'subject_type' => 'user',
            'subject_id' => $teacher->id,
            'created_at' => now()
        ]);

        $this->telegramService->sendMessage($teacher->telegram_user_id, "❌ Talabingiz rad etildi.");
        $this->telegramService->sendMessage($admin->telegram_user_id, "✅ Ustoz rad etildi.");
    }

    public function previewMaterial(User $admin, int $materialId): void
    {
        if (!$admin->isAdmin()) {
            $this->telegramService->sendMessage($admin->telegram_user_id, "❌ Admin emas.");
            return;
        }

        $material = Material::findOrFail($materialId);

        // Audit log — admin preview
        AuditLog::create([
            'actor_id' => $admin->id,
            'action_type' => 'admin_preview',
            'subject_type' => 'material',
            'subject_id' => $material->id,
            'metadata' => json_encode(['reason' => 'audit']),
            'created_at' => now()
        ]);

        // Faylni jo'natish
        try {
            $file = $this->storageService->getFileContent($material->storage_path);
            $this->telegramService->sendDocument(
                $admin->telegram_user_id,
                $file,
                $material->original_filename
            );
        } catch (\Exception $e) {
            $this->telegramService->sendMessage(
                $admin->telegram_user_id,
                "❌ Faylni jo'natishda xato: " . $e->getMessage()
            );
        }
    }

    public function blockUser(User $admin, int $userId, ?string $reason = null): void
    {
        if (!$admin->isAdmin()) {
            return;
        }

        $blockedUser = User::findOrFail($userId);

        BlockedUser::create([
            'user_id' => $userId,
            'blocked_by_admin_id' => $admin->id,
            'reason' => $reason,
            'blocked_at' => now()
        ]);

        $blockedUser->update(['status' => 'blocked']);

        AuditLog::create([
            'actor_id' => $admin->id,
            'action_type' => 'user_blocked',
            'subject_type' => 'user',
            'subject_id' => $userId,
            'metadata' => json_encode(['reason' => $reason]),
            'created_at' => now()
        ]);

        $this->telegramService->sendMessage($admin->telegram_user_id, "✅ Foydalanuvchi bloklandı.");
    }

    public function unblockUser(User $admin, int $userId): void
    {
        if (!$admin->isAdmin()) {
            return;
        }

        $blockedUser = User::findOrFail($userId);
        $blockedUser->update(['status' => 'active']);

        BlockedUser::where('user_id', $userId)->update(['unblock_at' => now()]);

        AuditLog::create([
            'actor_id' => $admin->id,
            'action_type' => 'user_unblocked',
            'subject_type' => 'user',
            'subject_id' => $userId,
            'created_at' => now()
        ]);

        $this->telegramService->sendMessage($admin->telegram_user_id, "✅ Foydalanuvchi blok ochilib tasdiqlandi.");
    }

    public function viewAuditLog(User $admin, int $limit = 20): void
    {
        if (!$admin->isAdmin()) {
            return;
        }

        $logs = AuditLog::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $text = "🧾 Audit jurnal (oxirgi {$limit}):\n\n";

        foreach ($logs as $log) {
            $actor = $log->actor ? $log->actor->full_name : 'System';
            $text .= "{$log->action_type} | {$actor} | {$log->created_at->format('H:i d.m')}\n";
        }

        $this->telegramService->sendMessage($admin->telegram_user_id, $text);
    }
}
