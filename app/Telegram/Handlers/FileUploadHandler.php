<?php

namespace App\Telegram\Handlers;

use App\Http\Controllers\TeacherController;
use App\Services\SessionService;
use App\Telegram\TelegramClient;
use App\Telegram\UpdateContext;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ustoz yuborgan faylni Telegramdan yuklab olib, yuklash wizardiga uzatadi.
 */
class FileUploadHandler
{
    public function handle(UpdateContext $ctx, TelegramClient $client): void
    {
        $user = $ctx->user;

        if (! $user || $user->role !== 'teacher' || $user->status !== 'active') {
            $client->sendMessage($ctx->chatId, __('bot.upload_need_teacher'));

            return;
        }

        $session = SessionService::getSession($ctx->telegramUserId, 'upload');

        if (! $session || ($session['current_step'] ?? '') !== 'awaiting_file') {
            $client->sendMessage($ctx->chatId, __('bot.upload_need_start'));

            return;
        }

        // Yuklab olish biroz vaqt olishi mumkin: ustoz fayl qabul qilinganini darhol ko'rsin.
        $client->sendMessage($ctx->chatId, __('bot.file_downloading'));

        $fileInfo = $client->getFile((string) $ctx->fileId);

        if (! ($fileInfo['ok'] ?? false)) {
            $client->sendMessage($ctx->chatId, __('bot.upload_tg_info_failed'));

            return;
        }

        $filePath = $fileInfo['result']['file_path'] ?? null;

        if (! $filePath) {
            $client->sendMessage($ctx->chatId, __('bot.upload_path_missing'));

            return;
        }

        $content = $client->downloadFile($filePath);

        if ($content === null) {
            $client->sendMessage($ctx->chatId, __('bot.upload_download_failed'));

            return;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'telegram_upload_');

        if ($tempPath === false || file_put_contents($tempPath, $content) === false) {
            $client->sendMessage($ctx->chatId, __('bot.upload_temp_failed'));

            if ($tempPath !== false) {
                @unlink($tempPath);
            }

            return;
        }

        try {
            $filename = $ctx->rawUpdate['message']['document']['file_name'] ?? basename($filePath);

            app(TeacherController::class)->uploadFile($user, [
                'file' => $tempPath,
                'filename' => $filename,
            ]);
        } catch (Throwable $e) {
            Log::error('Teacher file upload failed', [
                'telegram_user_id' => $ctx->telegramUserId,
                'error' => $e->getMessage(),
            ]);

            $client->sendMessage($ctx->chatId, __('bot.upload_save_failed'));
        } finally {
            @unlink($tempPath);
        }
    }
}
