<?php

namespace App\Telegram\Handlers;

use App\Http\Controllers\TeacherController;
use App\Services\SessionService;
use App\Telegram\TelegramClient;
use App\Telegram\UpdateContext;

/**
 * Yuklash wizardi davomida kelgan matnlar (sarlavha, tavsif, ...).
 * Matn shu handler tomonidan qabul qilinsa true qaytaradi.
 */
class UploadInputHandler
{
    private const METADATA_STEPS = [
        'enter_title',
        'enter_description',
        'enter_category',
        'enter_visibility',
        'confirm',
    ];

    public function handle(UpdateContext $ctx, TelegramClient $client): bool
    {
        if ($ctx->messageText === null) {
            return false;
        }

        $session = SessionService::getSession($ctx->telegramUserId, 'upload');

        if (! $session) {
            return false;
        }

        $step = $session['current_step'] ?? '';

        if (in_array($step, self::METADATA_STEPS, true)) {
            $user = $ctx->user;

            if (! $user || $user->role !== 'teacher' || $user->status !== 'active') {
                SessionService::clearSession($ctx->telegramUserId, 'upload');
                $client->sendMessage($ctx->chatId, __('bot.upload_need_active_teacher'));

                return true;
            }

            app(TeacherController::class)->handleUploadText($ctx->telegramUserId, $ctx->messageText);

            return true;
        }

        // Ustoz "Fayl yuklash"ni bosdi, lekin fayl o'rniga matn yubordi: jim qolmaymiz.
        if ($step === 'awaiting_file') {
            $client->sendMessage($ctx->chatId, __('bot.awaiting_file_hint'));

            return true;
        }

        return false;
    }
}
