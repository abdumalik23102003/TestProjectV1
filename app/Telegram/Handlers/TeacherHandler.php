<?php

namespace App\Telegram\Handlers;

use App\Http\Controllers\TeacherController;
use App\Models\User;
use App\Models\TeacherStudent;
use App\Services\SessionService;
use App\Services\AuditService;
use App\Services\InviteService;
use App\Services\ConnectionService;
use App\Telegram\UpdateContext;
use App\Telegram\TelegramClient;
use App\Telegram\Keyboard;

class TeacherHandler
{
    public function handle(
        UpdateContext $ctx,
        TelegramClient $client,
        array $parts
    ): void {
        $action = $parts[1] ?? null;
        $telegramUserId = $ctx->telegramUserId;

        // Upload callbacklari uchun foydalanuvchini tekshiramiz.
        if (in_array($action, ['save', 'cancel', 'edit', 'back'], true)) {
            $user = User::where('telegram_user_id', $telegramUserId)->first();

            if (!$user || $user->role !== 'teacher' || $user->status !== 'active') {
                $client->answerCallbackQuery(
                    $this->getCallbackId($ctx),
                    '❌ Ustoz ruxsati talab qilinadi.',
                    true
                );
                return;
            }

            $controller = app(TeacherController::class);

            match ($action) {
                'save' => $controller->confirmUpload($telegramUserId),
                'cancel' => $controller->cancelWizard($telegramUserId),
                'edit' => $controller->editUpload($telegramUserId),
                'back' => $controller->editUpload($telegramUserId),
            };

            $client->answerCallbackQuery($this->getCallbackId($ctx));
            return;
        }

        match ($action) {
            'upload' => $this->upload($ctx, $client),
            'files', 'materials' => $this->listFiles($ctx, $client),
            'students' => $this->listStudents($ctx, $client),
            'students_pending' => $this->listPendingStudents($ctx, $client),
            'approve_student' => $this->approveStudent($ctx, $client, (int)($parts[2] ?? 0)),
            'reject_student' => $this->rejectStudent($ctx, $client, (int)($parts[2] ?? 0)),
            'remove_student' => $this->removeStudent($ctx, $client, (int)($parts[2] ?? 0)),
            'link' => $this->showInviteLink($ctx, $client),
            default => $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                __('bot.unknown_action')
            ),
        };
    }

    protected function upload(UpdateContext $ctx, TelegramClient $client): void
    {
        $user = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        if (!$user || $user->role !== 'teacher' || $user->status !== 'active') {
            $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                '❌ Tasdiqlangan ustoz bo\'lishingiz kerak.',
                true
            );
            return;
        }

        SessionService::startWizard(
            $ctx->telegramUserId,
            'upload',
            'awaiting_file'
        );

        // Log upload start
        app(AuditService::class)->log('upload_started', 'material', null, $user);

        $client->sendMessage(
            $ctx->chatId,
            '📤 Iltimos, yuklamoqchi bo\'lgan PDF yoki boshqa ruxsat etilgan faylingizni yuboring.'
        );

        $client->answerCallbackQuery($this->getCallbackId($ctx));
    }

    protected function listFiles(UpdateContext $ctx, TelegramClient $client): void
    {
        $user = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        if (!$user) {
            $client->sendMessage($ctx->chatId, 'Foydalanuvchi topilmadi.');
            return;
        }

        app(TeacherController::class)->listMaterials($user);
    }

    protected function listStudents(UpdateContext $ctx, TelegramClient $client): void
    {
        $user = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        if (!$user || $user->role !== 'teacher') {
            $client->sendMessage($ctx->chatId, __('bot.unauthorized'));
            return;
        }

        $inviteService = app(InviteService::class);
        $students = $inviteService->getActiveStudents($user, 20);

        if ($students->isEmpty()) {
            $client->sendMessage($ctx->chatId, __('bot.teacher_no_students'));
            return;
        }

        $text = "✅ <b>Faol O'quvchilar</b> ({$students->count()} ta)\n\n";

        foreach ($students as $conn) {
            $student = $conn->student;
            $text .= "• {$student->full_name}\n";
        }

        $buttons = [
            [Keyboard::button(__('bot.btn_back'), 'menu:main')],
        ];

        $client->sendMessage($ctx->chatId, $text, $buttons);
    }

    protected function listPendingStudents(UpdateContext $ctx, TelegramClient $client): void
    {
        $user = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        if (!$user || $user->role !== 'teacher') {
            $client->sendMessage($ctx->chatId, __('bot.unauthorized'));
            return;
        }

        $inviteService = app(InviteService::class);
        $pending = $inviteService->getStudentRequests($user, 20);

        if ($pending->isEmpty()) {
            $client->sendMessage($ctx->chatId, __('bot.no_pending_requests'));
            return;
        }

        $text = "⏳ <b>Kutilayotgan So'rovlar</b> ({$pending->count()} ta)\n\n";

        $keyboard = [];
        foreach ($pending as $conn) {
            $student = $conn->student;
            $text .= "• {$student->full_name}\n";
            $keyboard[] = [
                Keyboard::button('✅ ' . $student->full_name, 'teacher:approve_student:' . $conn->id),
            ];
        }

        $keyboard[] = [Keyboard::button(__('bot.btn_back'), 'menu:main')];

        $client->sendMessage($ctx->chatId, $text, $keyboard);
    }

    protected function approveStudent(UpdateContext $ctx, TelegramClient $client, int $connectionId): void
    {
        $user = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        if (!$user || $user->role !== 'teacher') {
            $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.unauthorized'), true);
            return;
        }

        $connection = TeacherStudent::find($connectionId);

        if (!$connection || $connection->teacher_id !== $user->id) {
            $client->answerCallbackQuery($this->getCallbackId($ctx), 'Bog\'lanish topilmadi.', true);
            return;
        }

        $connectionService = app(ConnectionService::class);
        $connectionService->approveStudentRequest($user, $connection->student);

        $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.student_approved'));

        $client->sendMessage(
            $ctx->chatId,
            "✅ <b>{$connection->student->full_name}</b> tasdiqlandi."
        );
    }

    protected function rejectStudent(UpdateContext $ctx, TelegramClient $client, int $connectionId): void
    {
        $user = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        if (!$user || $user->role !== 'teacher') {
            $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.unauthorized'), true);
            return;
        }

        $connection = TeacherStudent::find($connectionId);

        if (!$connection || $connection->teacher_id !== $user->id) {
            $client->answerCallbackQuery($this->getCallbackId($ctx), 'Bog\'lanish topilmadi.', true);
            return;
        }

        $connectionService = app(ConnectionService::class);
        $connectionService->rejectStudentRequest($user, $connection->student);

        $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.student_rejected'));

        $client->sendMessage(
            $ctx->chatId,
            "❌ <b>{$connection->student->full_name}</b> rad etildi."
        );
    }

    protected function removeStudent(UpdateContext $ctx, TelegramClient $client, int $connectionId): void
    {
        $user = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        if (!$user || $user->role !== 'teacher') {
            $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.unauthorized'), true);
            return;
        }

        $connection = TeacherStudent::find($connectionId);

        if (!$connection || $connection->teacher_id !== $user->id) {
            $client->answerCallbackQuery($this->getCallbackId($ctx), 'Bog\'lanish topilmadi.', true);
            return;
        }

        $connectionService = app(ConnectionService::class);
        $connectionService->removeStudentAccess($user, $connection->student);

        $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.student_removed'));

        $client->sendMessage(
            $ctx->chatId,
            "🗑 <b>{$connection->student->full_name}</b> udalindi."
        );
    }

    protected function showInviteLink(UpdateContext $ctx, TelegramClient $client): void
    {
        $user = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        if (!$user || $user->role !== 'teacher' || $user->status !== 'active') {
            $client->sendMessage($ctx->chatId, __('bot.unauthorized'));
            return;
        }

        $connectionService = app(ConnectionService::class);
        $link = $connectionService->getInviteLink($user);

        $text = __('bot.invite_link') . "\n\n";
        $text .= "<code>{$link}</code>\n\n";
        $text .= __('bot.copy_link');

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
