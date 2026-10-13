<?php

namespace App\Telegram\Handlers;

use App\Http\Controllers\TeacherController;
use App\Models\User;
use App\Services\SessionService;
use App\Services\AuditService;
use App\Telegram\UpdateContext;
use App\Telegram\TelegramClient;

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
                '❌ Tasdiqlangan ustoz bo‘lishingiz kerak.',
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
            '📤 Iltimos, yuklamoqchi bo‘lgan PDF yoki boshqa ruxsat etilgan faylingizni yuboring.'
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

    protected function getCallbackId(UpdateContext $ctx): string
    {
        return $ctx->rawUpdate['callback_query']['id'] ?? '';
    }
}
