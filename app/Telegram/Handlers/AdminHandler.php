<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use App\Telegram\UpdateContext;
use App\Telegram\TelegramClient;
use App\Telegram\Keyboard;
use App\Telegram\Menus;
use App\Services\AuditService;
use App\Services\AdminPanelService;

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
                __('bot.unauthorized'),
                true
            );
            return;
        }

        $action = $parts[1] ?? null;

        match ($action) {
            'approve_teachers' => $this->approvePendingTeachers($ctx, $client),
            'approve_teacher' => $this->approveTeacher($ctx, $client, (int)($parts[2] ?? 0)),
            'reject_teacher' => $this->rejectTeacher($ctx, $client, (int)($parts[2] ?? 0)),
            'users' => $this->listUsers($ctx, $client),
            'users_active' => $this->listActiveUsers($ctx, $client),
            'users_blocked' => $this->listBlockedUsers($ctx, $client),
            'user_block' => $this->blockUserMenu($ctx, $client, (int)($parts[2] ?? 0)),
            'user_unblock' => $this->unblockUser($ctx, $client, (int)($parts[2] ?? 0)),
            'user_details' => $this->showUserDetails($ctx, $client, (int)($parts[2] ?? 0)),
            'audit' => $this->viewAuditLog($ctx, $client),
            'stats' => $this->showStatistics($ctx, $client),
            default => $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                __('bot.unknown_action')
            ),
        };
    }

    protected function approvePendingTeachers(UpdateContext $ctx, TelegramClient $client): void
    {
        $adminService = app(AdminPanelService::class);
        $pending = $adminService->getPendingTeachers(20);

        if ($pending->isEmpty()) {
            $client->sendMessage($ctx->chatId, __('bot.no_pending'));
            return;
        }

        $text = "⏳ <b>Tasdiqqa Kutayotgan Ustozlar</b> ({$pending->count()} ta)\n\n";

        $keyboard = [];
        foreach ($pending as $teacher) {
            $text .= "• {$teacher->full_name}\n";
            $keyboard[] = [
                Keyboard::button(
                    '✅ ' . $teacher->full_name,
                    'admin:approve_teacher:' . $teacher->id
                ),
            ];
        }

        $keyboard[] = [Keyboard::button(__('bot.btn_back'), 'menu:main')];

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
                __('bot.teacher_not_found'),
                true
            );
            return;
        }

        $adminService = app(AdminPanelService::class);
        $adminService->approveTeacher($teacher);

        // Log approval
        $admin = User::where('telegram_user_id', $ctx->telegramUserId)->first();
        if ($admin) {
            app(AuditService::class)->logTeacherApproved($admin, $teacher);
        }

        $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.teacher_approved'));

        // Notify admin
        $client->sendMessage(
            $ctx->chatId,
            "✅ <b>{$teacher->full_name}</b> tasdiqlandi."
        );

        // Notify teacher
        $client->sendMessage(
            $teacher->telegram_user_id,
            "✅ Tabriklaymiz! Sizning ustozlik akkauntingiz tasdiqlandi. /start bosing."
        );
    }

    protected function rejectTeacher(
        UpdateContext $ctx,
        TelegramClient $client,
        int $teacherId
    ): void
    {
        $teacher = User::where('id', $teacherId)
            ->where('role', 'teacher')
            ->where('status', 'pending')
            ->first();

        if (!$teacher) {
            $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                __('bot.teacher_not_found'),
                true
            );
            return;
        }

        $adminService = app(AdminPanelService::class);
        $adminService->rejectTeacher($teacher, 'Admin tomonidan rad etildi');

        // Log rejection
        $admin = User::where('telegram_user_id', $ctx->telegramUserId)->first();
        if ($admin) {
            app(AuditService::class)->logTeacherRejected($admin, $teacher, 'Admin decision');
        }

        $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.teacher_rejected'));

        // Notify admin
        $client->sendMessage(
            $ctx->chatId,
            "❌ <b>{$teacher->full_name}</b> rad etildi."
        );

        // Notify teacher
        $client->sendMessage(
            $teacher->telegram_user_id,
            "❌ Uzur, sizning ustozlik talabingiz rad etildi. Qayta urinishni xohlaysizmi? /start bosing."
        );
    }

    protected function listUsers(UpdateContext $ctx, TelegramClient $client): void
    {
        $adminService = app(AdminPanelService::class);
        $stats = $adminService->getUserStats();

        $text = "👥 <b>Foydalanuvchilar Statistikasi</b>\n\n";
        $text .= "📊 <b>Jami:</b> {$stats['total']} ta\n";
        $text .= "✅ <b>Faol:</b> {$stats['active']} ta\n";
        $text .= "⏳ <b>Kutilmoqda:</b> {$stats['pending']} ta\n";
        $text .= "🚫 <b>Bloklandi:</b> {$stats['blocked']} ta\n\n";
        $text .= "👨‍🏫 <b>Ustozlar:</b> {$stats['teachers']} ta\n";
        $text .= "🎓 <b>O'quvchilar:</b> {$stats['students']} ta\n";
        $text .= "👤 <b>Adminlar:</b> {$stats['admins']} ta\n";

        $buttons = [
            [Keyboard::button('✅ Faol', 'admin:users_active')],
            [Keyboard::button('🚫 Bloklandi', 'admin:users_blocked')],
            [Keyboard::button(__('bot.btn_back'), 'menu:main')],
        ];

        $client->editMessageText($ctx->chatId, $ctx->messageId, $text, $buttons);
    }

    protected function listActiveUsers(UpdateContext $ctx, TelegramClient $client): void
    {
        $adminService = app(AdminPanelService::class);
        $users = $adminService->getActiveUsers(15);

        if ($users->isEmpty()) {
            $client->editMessageText(
                $ctx->chatId,
                $ctx->messageId,
                __('bot.no_results')
            );
            return;
        }

        $text = "✅ <b>Faol Foydalanuvchilar</b> ({$users->count()} ta)\n\n";
        $text .= $adminService->formatUsersList($users);

        $buttons = [
            [Keyboard::button(__('bot.btn_back'), 'admin:users')],
        ];

        $client->editMessageText($ctx->chatId, $ctx->messageId, $text, $buttons);
    }

    protected function listBlockedUsers(UpdateContext $ctx, TelegramClient $client): void
    {
        $adminService = app(AdminPanelService::class);
        $users = $adminService->getBlockedUsers(15);

        if ($users->isEmpty()) {
            $client->sendMessage($ctx->chatId, __('bot.no_blocked_users'));
            return;
        }

        $text = "🚫 <b>Bloklangan Foydalanuvchilar</b> ({$users->count()} ta)\n\n";
        $text .= $adminService->formatUsersList($users);

        $buttons = [];
        foreach ($users as $user) {
            $buttons[] = [
                Keyboard::button(
                    '✅ ' . $user->full_name,
                    'admin:user_unblock:' . $user->id
                ),
            ];
        }
        $buttons[] = [Keyboard::button(__('bot.btn_back'), 'admin:users')];

        $client->sendMessage($ctx->chatId, $text, $buttons);
    }

    protected function blockUserMenu(
        UpdateContext $ctx,
        TelegramClient $client,
        int $userId
    ): void
    {
        $adminService = app(AdminPanelService::class);
        $user = $adminService->getUserById($userId);

        if (!$user) {
            $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                __('bot.user_not_found'),
                true
            );
            return;
        }

        if ($user->status === 'blocked') {
            $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                __('bot.user_already_blocked'),
                true
            );
            return;
        }

        $adminService->blockUser($user);

        // Log block
        $admin = User::where('telegram_user_id', $ctx->telegramUserId)->first();
        if ($admin) {
            app(AuditService::class)->logUserBlocked($admin, $user, 'Admin decision');
        }

        $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.user_blocked'));

        $client->sendMessage(
            $ctx->chatId,
            "🚫 <b>{$user->full_name}</b> bloklandi."
        );
    }

    protected function unblockUser(
        UpdateContext $ctx,
        TelegramClient $client,
        int $userId
    ): void
    {
        $adminService = app(AdminPanelService::class);
        $user = $adminService->getUserById($userId);

        if (!$user) {
            $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                __('bot.user_not_found'),
                true
            );
            return;
        }

        $adminService->unblockUser($user);

        // Log unblock
        $admin = User::where('telegram_user_id', $ctx->telegramUserId)->first();
        if ($admin) {
            app(AuditService::class)->logUserUnblocked($admin, $user);
        }

        $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.user_unblocked'));

        $client->sendMessage(
            $ctx->chatId,
            "✅ <b>{$user->full_name}</b> blokdan chiqarildi."
        );
    }

    protected function showUserDetails(
        UpdateContext $ctx,
        TelegramClient $client,
        int $userId
    ): void
    {
        $adminService = app(AdminPanelService::class);
        $user = $adminService->getUserById($userId);

        if (!$user) {
            $client->sendMessage($ctx->chatId, __('bot.user_not_found'));
            return;
        }

        $text = "👤 <b>Foydalanuvchi Tafsilotlari</b>\n\n";
        $text .= "<b>Ism:</b> {$user->full_name}\n";
        $text .= "<b>Telegram ID:</b> {$user->telegram_user_id}\n";
        $text .= "<b>Rol:</b> {$user->role}\n";
        $text .= "<b>Status:</b> " . __("bot.status_{$user->status}") . "\n";
        $text .= "<b>Ro'yxatdan o'tish:</b> {$user->created_at->format('d.m.Y H:i')}\n";

        $buttons = [
            [
                Keyboard::button('ℹ️ Tafsilot', 'admin:user_details:' . $user->id),
                Keyboard::button(__('bot.btn_back'), 'admin:users'),
            ],
        ];

        if ($user->status !== 'blocked') {
            $buttons[] = [Keyboard::button(__('bot.btn_block'), 'admin:user_block:' . $user->id)];
        } else {
            $buttons[] = [Keyboard::button(__('bot.btn_unblock'), 'admin:user_unblock:' . $user->id)];
        }

        $client->sendMessage($ctx->chatId, $text, $buttons);
    }

    protected function viewAuditLog(UpdateContext $ctx, TelegramClient $client): void
    {
        $logs = app(AuditService::class)->getLatest(10);

        if ($logs->isEmpty()) {
            $client->editMessageText(
                $ctx->chatId,
                $ctx->messageId,
                "💻 " . __('bot.no_audit_logs')
            );
            return;
        }

        $text = "💾 <b>Oxirgi Audit Loglar</b>\n\n";

        foreach ($logs as $log) {
            $text .= app(AuditService::class)->formatForDisplay($log) . "\n";
        }

        $buttons = [
            [Keyboard::button(__('bot.btn_back'), 'menu:main')],
        ];

        $client->editMessageText($ctx->chatId, $ctx->messageId, $text, $buttons);
    }

    protected function showStatistics(UpdateContext $ctx, TelegramClient $client): void
    {
        $adminService = app(AdminPanelService::class);
        $stats = $adminService->getSystemStats();

        $text = "📊 <b>Sistema Statistikasi</b>\n\n";
        $text .= "<b>Foydalanuvchilar:</b>\n";
        $text .= "  • Jami: {$stats['users']['total']}\n";
        $text .= "  • Faol: {$stats['users']['active']}\n";
        $text .= "  • Kutilmoqda: {$stats['users']['pending']}\n";
        $text .= "  • Bloklandi: {$stats['users']['blocked']}\n\n";
        $text .= "<b>Materiallar:</b> {$stats['total_materials']} ta\n";
        $text .= "<b>Audit Loglar:</b> {$stats['total_audit_logs']} ta\n";

        if (!empty($stats['audit_actions_7days'])) {
            $text .= "\n<b>Oxirgi 7 kunlik Amallar:</b>\n";
            foreach ($stats['audit_actions_7days'] as $action => $count) {
                $text .= "  • {$action}: {$count}\n";
            }
        }

        $buttons = [
            [Keyboard::button(__('bot.btn_back'), 'menu:main')],
        ];

        $client->sendMessage($ctx->chatId, $text, $buttons);
    }

    protected function getCallbackId(UpdateContext $ctx): string
    {
        return $ctx->rawUpdate['callback_query']['id'] ?? '';
    }
}
