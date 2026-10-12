<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TelegramService;
use App\Services\SessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    protected TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    public function handleStart(int $telegramUserId, ?string $startParam = null): void
    {
        $user = User::firstOrCreate(
            ['telegram_user_id' => $telegramUserId],
            ['full_name' => '?', 'role' => 'student', 'status' => 'pending']
        );

        // Deep-link check: /start join_TOKEN
        if ($startParam && str_contains($startParam, 'join_')) {
            $this->handleDeepLink($user, $startParam);
            return;
        }

        $this->showRoleSelection($telegramUserId);
    }

    private function handleDeepLink(User $user, string $startParam): void
    {
        $token = str_replace('join_', '', $startParam);
        $teacher = User::where('teacher_approval_token', $token)
            ->where('status', 'active')
            ->first();

        if ($teacher) {
            \App\Models\TeacherStudent::firstOrCreate(
                ['teacher_id' => $teacher->id, 'student_id' => $user->id],
                ['status' => 'pending', 'requested_at' => now()]
            );

            $this->telegramService->sendMessage(
                $user->telegram_user_id,
                "✅ So'rov jo'natildi. Ustoz tasdiqlashini kuting."
            );
            return;
        }

        $this->telegramService->sendMessage($user->telegram_user_id, "❌ Noto'g'ri havola.");
    }

    public function showRoleSelection(int $telegramUserId): void
    {
        $buttons = [
            ['text' => '👨‍🏫 Ustozman', 'callback_data' => 'role:teacher'],
            ['text' => '🎓 O\'quvchiman', 'callback_data' => 'role:student'],
            ['text' => 'ℹ️ Yordam', 'callback_data' => 'help'],
        ];

        $this->telegramService->sendMessage($telegramUserId, "Rolni tanlang:", $buttons);
    }

    public function handleRoleSelection(int $telegramUserId, string $role): void
    {
        SessionService::startWizard($telegramUserId, 'registration', 'enter_name', ['role' => $role]);
        $this->telegramService->sendMessage($telegramUserId, "Iltimos, to'liq ismingizni kiriting:");
    }

    public function handleNameInput(int $telegramUserId, string $fullName): void
    {
        $user = User::where('telegram_user_id', $telegramUserId)->firstOrFail();
        $session = SessionService::getSession($telegramUserId, 'registration');

        $user->update([
            'full_name' => trim($fullName),
            'role' => $session['role'] ?? 'student'
        ]);

        if ($user->isTeacher()) {
            $user->status = 'pending';
            $user->teacher_approval_token = Str::random(32);
            $user->save();

            $this->telegramService->sendMessage(
                $telegramUserId,
                "✅ Ro'yxatdan o'tishingiz yakunlandi. Admin tasdiqini kuting."
            );
        } else {
            $user->status = 'active';
            $user->save();
            $this->showStudentMenu($telegramUserId);
        }

        SessionService::clearSession($telegramUserId, 'registration');
    }

    public function showStudentMenu(int $telegramUserId): void
    {
        $buttons = [
            ['text' => '🔎 Material qidirish', 'callback_data' => 'student:search'],
            ['text' => '👨‍🏫 Mening ustozlarim', 'callback_data' => 'student:teachers'],
            ['text' => '📥 Oxirgi yuklamalar', 'callback_data' => 'student:recent'],
            ['text' => '👤 Profil', 'callback_data' => 'student:profile'],
            ['text' => 'ℹ️ Yordam', 'callback_data' => 'help'],
        ];

        $this->telegramService->sendMessage($telegramUserId, "🎓 O'quvchi menyusi:", $buttons);
    }

    public function showTeacherMenu(int $telegramUserId): void
    {
        $buttons = [
            ['text' => '➕ Fayl yuklash', 'callback_data' => 'teacher:upload'],
            ['text' => '📚 Mening fayllarim', 'callback_data' => 'teacher:materials'],
            ['text' => '🔎 Material qidirish', 'callback_data' => 'teacher:search'],
            ['text' => '👥 O\'quvchilarim', 'callback_data' => 'teacher:students'],
            ['text' => '📊 Statistika', 'callback_data' => 'teacher:stats'],
            ['text' => '⚙️ Sozlamalar', 'callback_data' => 'teacher:settings'],
        ];

        $this->telegramService->sendMessage($telegramUserId, "👨‍🏫 Ustoz menyusi:", $buttons);
    }

    public function showAdminMenu(int $telegramUserId): void
    {
        $buttons = [
            ['text' => '⏳ Ustozlarni tasdiqlash', 'callback_data' => 'admin:approve_teachers'],
            ['text' => '👥 Foydalanuvchilar', 'callback_data' => 'admin:users'],
            ['text' => '🗂 Kategoriyalar', 'callback_data' => 'admin:categories'],
            ['text' => '📏 Fayl limitlari', 'callback_data' => 'admin:limits'],
            ['text' => '🧾 Audit jurnali', 'callback_data' => 'admin:audit'],
            ['text' => '🚫 Bloklanganlar', 'callback_data' => 'admin:blocked'],
        ];

        $this->telegramService->sendMessage($telegramUserId, "⚙️ Admin menyusi:", $buttons);
    }
}
