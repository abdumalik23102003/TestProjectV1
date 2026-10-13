<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use App\Telegram\UpdateContext;
use App\Telegram\TelegramClient;
use App\Telegram\Keyboard;
use App\Services\AuditService;

class AdminHandler
{
    public function handle(UpdateContext $ctx, TelegramClient $client, array $parts): void
    {
        $user = User::where('telegram_user_id', $ctx->telegramUserId)
            ->where('role', 'admin')
            ->where('status', 'active')
            ->first();

        if (!$user) {
            $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                'Bu amal faqat administrator uchun.'
            );

            return;
        }
        $action = $parts[1] ?? null;

        match ($action) {
            'approve_teachers' => $this->approvePendingTeachers($ctx, $client),
            'approve_teacher' => $this->approveTeacher($ctx, $client, (int)($parts[2] ?? 0)),
            'users' => $this->listUsers($ctx, $client),
            'audit' => $this->viewAuditLog($ctx, $client),
            default => $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                __('bot.unknown_action')
            ),
        };
    }


    protected function approvePendingTeachers(UpdateContext $ctx, TelegramClient $client): void
    {
        $pending = User::where('role', 'teacher')
            ->where('status', 'pending')
            ->get();

        if ($pending->isEmpty()) {
            $client->sendMessage($ctx->chatId, __('bot.no_pending'));
            return;
        }

        $text = __('bot.pending_teachers', [
            'count' => $pending->count(),
            'teachers' => 'Quyidagi ustozlarni tasdiqlash mumkin:',
        ]);

        $keyboard = [];

        foreach ($pending as $teacher) {
            $keyboard[] = [
                Keyboard::button(
                    '✅ ' . $teacher->full_name,
                    'admin:approve_teacher:' . $teacher->id
                ),
            ];
        }

        $client->sendMessage($ctx->chatId, $text, $keyboard);
    }

    protected function approveTeacher(
        UpdateContext  $ctx,
        TelegramClient $client,
        int            $teacherId
    ): void
    {
        $teacher = User::where('id', $teacherId)
            ->where('role', 'teacher')
            ->where('status', 'pending')
            ->first();

        if (!$teacher) {
            $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                'Ustoz topilmadi yoki allaqachon tasdiqlangan.'
            );
            return;
        }

        $teacher->status = 'active';
        $teacher->teacher_approved_at = now();
        $teacher->save();

        // Log approval
        $admin = User::where('telegram_user_id', $ctx->telegramUserId)->first();
        if ($admin) {
            app(AuditService::class)->logTeacherApproved($admin, $teacher);
        }

        // Admin tasdiqlaganini tasdiqlash
        $client->answerCallbackQuery(
            $this->getCallbackId($ctx),
            'Ustoz tasdiqlandi!'
        );

        // Adminga xabar
        $client->sendMessage(
            $ctx->chatId,
            '✅ ' . $teacher->full_name . ' tasdiqlandi.'
        );

        // Ustozga bildirishnoma
        $client->sendMessage(
            $teacher->telegram_user_id,
            "✅ Tabriklaymiz! Sizning ustozlik akkauntingiz tasdiqlandi."
        );

        // Ustoz menyusini ochish
        app(\App\Http\Controllers\AuthController::class)
            ->showTeacherMenu((int)$teacher->telegram_user_id);
    }

    protected function listUsers(UpdateContext $ctx, TelegramClient $client): void
    {
        $client->sendMessage($ctx->chatId, "👥 Foydalanuvchilar...");
    }

    protected function viewAuditLog(UpdateContext $ctx, TelegramClient $client): void
    {
        $logs = app(AuditService::class)->getLatest(10);

        if ($logs->isEmpty()) {
            $client->sendMessage($ctx->chatId, "💻 Hali audit loglar yo'q.");
            return;
        }

        $text = "💾 <b>Oxirgi Audit Loglar</b>\n\n";

        foreach ($logs as $log) {
            $text .= app(AuditService::class)->formatForDisplay($log) . "\n";
        }

        $client->sendMessage($ctx->chatId, $text);
    }

    protected function getCallbackId(UpdateContext $ctx): string
    {
        return $ctx->rawUpdate['callback_query']['id'] ?? '';
    }
}
