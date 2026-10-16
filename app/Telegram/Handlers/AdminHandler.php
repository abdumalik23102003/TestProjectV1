<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use App\Services\AdminPanelService;
use App\Services\AuditService;
use App\Telegram\Keyboard;
use App\Telegram\TelegramClient;
use App\Telegram\UpdateContext;
use Illuminate\Support\Facades\Lang;

/**
 * Admin callbacklari: admin:<amal>[:<id>]
 * Har bir chaqiruvda admin roli va faol holati qayta tekshiriladi.
 */
class AdminHandler
{
    private bool $answered = false;

    public function handle(UpdateContext $ctx, TelegramClient $client, array $parts): void
    {
        $this->answered = false;

        $admin = User::where('telegram_user_id', $ctx->telegramUserId)
            ->where('role', 'admin')
            ->where('status', 'active')
            ->first();

        if (! $admin) {
            $this->answer($ctx, $client, __('bot.unauthorized'), true);

            return;
        }

        $action = $parts[1] ?? null;
        $id = (int) ($parts[2] ?? 0);

        match ($action) {
            'approve_teachers' => $this->pendingTeachers($ctx, $client),
            'approve_teacher' => $this->approveTeacher($ctx, $client, $admin, $id),
            'reject_teacher' => $this->rejectTeacher($ctx, $client, $admin, $id),
            'users' => $this->usersOverview($ctx, $client),
            'users_active' => $this->usersActive($ctx, $client),
            'users_blocked' => $this->usersBlocked($ctx, $client),
            'user_details' => $this->userDetails($ctx, $client, $admin, $id),
            'user_block' => $this->blockConfirm($ctx, $client, $admin, $id),
            'user_block_do' => $this->blockDo($ctx, $client, $admin, $id),
            'user_unblock' => $this->unblock($ctx, $client, $admin, $id),
            'audit' => $this->audit($ctx, $client),
            'stats' => $this->stats($ctx, $client),
            default => $this->answer($ctx, $client, __('bot.unknown_action')),
        };

        // Tugma "aylanib" qolmasligi uchun: hali javob berilmagan bo'lsa, jim javob beramiz.
        $this->answer($ctx, $client);
    }

    // ---------------------------------------------------------------- ustozlar

    private function pendingTeachers(UpdateContext $ctx, TelegramClient $client): void
    {
        $pending = $this->panel()->getPendingTeachers(20);

        if ($pending->isEmpty()) {
            $this->show($ctx, $client, __('bot.no_pending'), [[Keyboard::button(__('bot.btn_back'), 'menu:main')]]);

            return;
        }

        $text = __('bot.admin_title_pending', ['count' => $pending->count()])."\n\n";
        $buttons = [];

        foreach ($pending as $teacher) {
            $text .= '• '.e($teacher->full_name)."\n";
            $buttons[] = [
                Keyboard::button('✅ '.$this->short($teacher->full_name), 'admin:approve_teacher:'.$teacher->id),
                Keyboard::button('❌', 'admin:reject_teacher:'.$teacher->id),
            ];
        }

        $buttons[] = [Keyboard::button(__('bot.btn_back'), 'menu:main')];

        $this->show($ctx, $client, $text, $buttons);
    }

    private function approveTeacher(UpdateContext $ctx, TelegramClient $client, User $admin, int $id): void
    {
        $teacher = $this->pendingTeacher($id);

        if (! $teacher) {
            $this->answer($ctx, $client, __('bot.teacher_not_found'), true);
            $this->pendingTeachers($ctx, $client);

            return;
        }

        $this->panel()->approveTeacher($teacher);
        $this->audit()->logTeacherApproved($admin, $teacher);

        $this->answer($ctx, $client, __('bot.teacher_approved'));
        $client->sendMessage($teacher->telegram_user_id, __('bot.notice_teacher_approved'));

        $this->pendingTeachers($ctx, $client);
    }

    private function rejectTeacher(UpdateContext $ctx, TelegramClient $client, User $admin, int $id): void
    {
        $teacher = $this->pendingTeacher($id);

        if (! $teacher) {
            $this->answer($ctx, $client, __('bot.teacher_not_found'), true);
            $this->pendingTeachers($ctx, $client);

            return;
        }

        $this->panel()->rejectTeacher($teacher, 'admin_decision');
        $this->audit()->logTeacherRejected($admin, $teacher, 'admin_decision');

        $this->answer($ctx, $client, __('bot.teacher_rejected'));
        $client->sendMessage($teacher->telegram_user_id, __('bot.notice_teacher_rejected'));

        $this->pendingTeachers($ctx, $client);
    }

    private function pendingTeacher(int $id): ?User
    {
        return User::where('id', $id)
            ->where('role', 'teacher')
            ->where('status', 'pending')
            ->first();
    }

    // ------------------------------------------------------- foydalanuvchilar

    private function usersOverview(UpdateContext $ctx, TelegramClient $client): void
    {
        $stats = $this->panel()->getUserStats();

        $text = __('bot.admin_title_users')."\n\n".__('bot.admin_users_summary', $stats);

        $this->show($ctx, $client, $text, [
            [Keyboard::button('✅ '.__('bot.status_active'), 'admin:users_active')],
            [Keyboard::button('🚫 '.__('bot.status_blocked'), 'admin:users_blocked')],
            [Keyboard::button(__('bot.btn_pending_teachers'), 'admin:approve_teachers')],
            [Keyboard::button(__('bot.btn_back'), 'menu:main')],
        ]);
    }

    private function usersActive(UpdateContext $ctx, TelegramClient $client): void
    {
        $users = $this->panel()->getActiveUsers(15);

        $this->showUserList($ctx, $client, $users, 'bot.admin_title_active');
    }

    private function usersBlocked(UpdateContext $ctx, TelegramClient $client): void
    {
        $users = $this->panel()->getBlockedUsers(15);

        if ($users->isEmpty()) {
            $this->show($ctx, $client, __('bot.no_blocked_users'), [[Keyboard::button(__('bot.btn_back'), 'admin:users')]]);

            return;
        }

        $this->showUserList($ctx, $client, $users, 'bot.admin_title_blocked');
    }

    private function showUserList(UpdateContext $ctx, TelegramClient $client, $users, string $titleKey): void
    {
        if ($users->isEmpty()) {
            $this->show($ctx, $client, __('bot.no_results'), [[Keyboard::button(__('bot.btn_back'), 'admin:users')]]);

            return;
        }

        $text = __($titleKey, ['count' => $users->count()])."\n\n".$this->panel()->formatUsersList($users);

        $buttons = [];

        foreach ($users as $user) {
            $buttons[] = [Keyboard::button('ℹ️ '.$this->short($user->full_name), 'admin:user_details:'.$user->id)];
        }

        $buttons[] = [Keyboard::button(__('bot.btn_back'), 'admin:users')];

        $this->show($ctx, $client, $text, $buttons);
    }

    private function userDetails(UpdateContext $ctx, TelegramClient $client, User $admin, int $id): void
    {
        $user = $this->panel()->getUserById($id);

        if (! $user) {
            $this->answer($ctx, $client, __('bot.user_not_found'), true);

            return;
        }

        $text = __('bot.admin_user_card', [
            'name' => e($user->full_name),
            'tid' => $user->telegram_user_id,
            'role' => $this->roleLabel($user->role),
            'status' => __("bot.status_{$user->status}"),
            'date' => $user->created_at->format('d.m.Y H:i'),
        ]);

        $buttons = [];

        if ($user->status === 'blocked') {
            $buttons[] = [Keyboard::button(__('bot.btn_unblock'), 'admin:user_unblock:'.$user->id)];
        } elseif ($this->panel()->cannotBlockReason($admin, $user) === null) {
            $buttons[] = [Keyboard::button(__('bot.btn_block'), 'admin:user_block:'.$user->id)];
        }

        $buttons[] = [Keyboard::button(__('bot.btn_back'), 'admin:users')];

        $this->show($ctx, $client, $text, $buttons);
    }

    private function blockConfirm(UpdateContext $ctx, TelegramClient $client, User $admin, int $id): void
    {
        $user = $this->panel()->getUserById($id);

        if (! $user) {
            $this->answer($ctx, $client, __('bot.user_not_found'), true);

            return;
        }

        if ($reason = $this->panel()->cannotBlockReason($admin, $user)) {
            $this->answer($ctx, $client, __($reason), true);

            return;
        }

        $this->show($ctx, $client, __('bot.admin_block_confirm', ['name' => e($user->full_name)]), [
            [
                Keyboard::button(__('bot.btn_block_yes'), 'admin:user_block_do:'.$user->id),
                Keyboard::button(__('bot.btn_cancel_no'), 'admin:user_details:'.$user->id),
            ],
        ]);
    }

    private function blockDo(UpdateContext $ctx, TelegramClient $client, User $admin, int $id): void
    {
        $user = $this->panel()->getUserById($id);

        if (! $user) {
            $this->answer($ctx, $client, __('bot.user_not_found'), true);

            return;
        }

        if ($reason = $this->panel()->cannotBlockReason($admin, $user)) {
            $this->answer($ctx, $client, __($reason), true);

            return;
        }

        $this->panel()->blockUser($user, 'admin_decision');
        $this->audit()->logUserBlocked($admin, $user, 'admin_decision');

        $this->answer($ctx, $client, __('bot.user_blocked'));
        $client->sendMessage($user->telegram_user_id, __('bot.blocked_user'));

        $this->userDetails($ctx, $client, $admin, $user->id);
    }

    private function unblock(UpdateContext $ctx, TelegramClient $client, User $admin, int $id): void
    {
        $user = $this->panel()->getUserById($id);

        if (! $user) {
            $this->answer($ctx, $client, __('bot.user_not_found'), true);

            return;
        }

        if ($user->status !== 'blocked') {
            $this->answer($ctx, $client, __('bot.unknown_action'), true);

            return;
        }

        $this->panel()->unblockUser($user);
        $this->audit()->logUserUnblocked($admin, $user);

        $this->answer($ctx, $client, __('bot.user_unblocked'));
        $client->sendMessage($user->telegram_user_id, __('bot.notice_user_unblocked'));

        $this->userDetails($ctx, $client, $admin, $user->id);
    }

    // ------------------------------------------------------- audit va statistika

    private function audit(): AuditService
    {
        return app(AuditService::class);
    }

    private function auditView(UpdateContext $ctx, TelegramClient $client): void
    {
        $logs = $this->audit()->getLatest(10);

        if ($logs->isEmpty()) {
            $this->show($ctx, $client, __('bot.no_audit_logs'), [[Keyboard::button(__('bot.btn_back'), 'menu:main')]]);

            return;
        }

        $text = __('bot.admin_title_audit')."\n\n";

        foreach ($logs as $log) {
            $text .= $this->audit()->formatForDisplay($log)."\n";
        }

        $this->show($ctx, $client, $text, [[Keyboard::button(__('bot.btn_back'), 'menu:main')]]);
    }

    private function stats(UpdateContext $ctx, TelegramClient $client): void
    {
        $stats = $this->panel()->getSystemStats();

        $text = __('bot.admin_title_stats')."\n\n".__('bot.admin_stats_body', [
            'users_total' => $stats['users']['total'],
            'users_active' => $stats['users']['active'],
            'users_pending' => $stats['users']['pending'],
            'users_blocked' => $stats['users']['blocked'],
            'materials' => $stats['total_materials'],
            'audits' => $stats['total_audit_logs'],
        ]);

        if (! empty($stats['audit_actions_7days'])) {
            $text .= "\n\n".__('bot.admin_stats_week')."\n";

            foreach ($stats['audit_actions_7days'] as $action => $count) {
                $key = "bot.audit_action_{$action}";
                $label = Lang::has($key) ? __($key) : e($action);
                $text .= "• {$label}: {$count}\n";
            }
        }

        $this->show($ctx, $client, $text, [[Keyboard::button(__('bot.btn_back'), 'menu:main')]]);
    }

    // ---------------------------------------------------------------- yordamchi

    private function panel(): AdminPanelService
    {
        return app(AdminPanelService::class);
    }

    private function show(UpdateContext $ctx, TelegramClient $client, string $text, array $buttons): void
    {
        $client->editOrSendMessage($ctx->chatId, $ctx->messageId, $text, $buttons);
    }

    /**
     * Callbackka bir marta javob beradi. Faqat to'g'ridan-to'g'ri "admin:" tugmalari uchun:
     * "menu:" orqali kelganlarga MenuHandler o'zi javob beradi.
     */
    private function answer(UpdateContext $ctx, TelegramClient $client, string $text = '', bool $alert = false): void
    {
        if ($this->answered || ! str_starts_with((string) $ctx->callbackData, 'admin:')) {
            return;
        }

        $callbackId = $ctx->rawUpdate['callback_query']['id'] ?? '';

        if ($callbackId === '') {
            return;
        }

        $this->answered = true;
        $client->answerCallbackQuery($callbackId, $text, $alert);
    }

    private function roleLabel(string $role): string
    {
        $key = "bot.admin_role_{$role}";

        return Lang::has($key) ? __($key) : e($role);
    }

    private function short(string $name): string
    {
        return mb_strimwidth($name, 0, 28, '…');
    }
}
