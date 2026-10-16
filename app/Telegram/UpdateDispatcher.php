<?php

namespace App\Telegram;

use App\Services\SessionService;
use App\Telegram\Handlers\AdminHandler;
use App\Telegram\Handlers\FileUploadHandler;
use App\Telegram\Handlers\HelpHandler;
use App\Telegram\Handlers\MenuHandler;
use App\Telegram\Handlers\RegistrationHandler;
use App\Telegram\Handlers\RoleHandler;
use App\Telegram\Handlers\SearchInputHandler;
use App\Telegram\Handlers\StartHandler;
use App\Telegram\Handlers\StudentHandler;
use App\Telegram\Handlers\TeacherHandler;
use App\Telegram\Handlers\UploadInputHandler;
use Illuminate\Support\Facades\Log;

/**
 * Kelgan yangilanishni mos handlerga yo'naltiradi.
 * Tartib: blok to'sig'i → /start → /help → ro'yxatdan o'tish → fayl → matn wizardlari → tugmalar.
 */
class UpdateDispatcher
{
    public function __construct(private TelegramClient $client) {}

    public function dispatch(UpdateContext $ctx): void
    {
        $client = $this->client;

        if ($this->stopBlockedUser($ctx)) {
            return;
        }

        if ($ctx->messageText !== null && str_starts_with($ctx->messageText, '/start')) {
            $param = trim(substr($ctx->messageText, strlen('/start')));

            app(StartHandler::class)->handle($ctx, $client, ['start', $ctx->telegramUserId, $param]);

            return;
        }

        if ($ctx->messageText === '/help') {
            $client->sendMessage($ctx->chatId, __('bot.help_text'));

            return;
        }

        $registration = SessionService::getSession($ctx->telegramUserId, 'registration');

        if ($registration && ($registration['current_step'] ?? null) === 'enter_name') {
            app(RegistrationHandler::class)->handleName($ctx, $client);

            return;
        }

        if ($ctx->isFile()) {
            app(FileUploadHandler::class)->handle($ctx, $client);

            return;
        }

        if ($ctx->messageText !== null) {
            if (app(UploadInputHandler::class)->handle($ctx, $client)) {
                return;
            }

            if (app(SearchInputHandler::class)->handle($ctx, $client)) {
                return;
            }
        }

        if ($ctx->isCallback()) {
            $this->router()->route($ctx, $client);

            return;
        }

        Log::info('Webhook: unhandled update', [
            'telegram_user_id' => $ctx->telegramUserId,
            'chat_id' => $ctx->chatId,
            'is_message' => $ctx->isMessage(),
            'is_callback' => $ctx->isCallback(),
        ]);
    }

    /**
     * Bloklangan foydalanuvchi hech qanday amalni bajara olmaydi (tugma, matn yoki fayl).
     */
    private function stopBlockedUser(UpdateContext $ctx): bool
    {
        if (! $ctx->user || $ctx->user->status !== 'blocked') {
            return false;
        }

        SessionService::clearSession($ctx->telegramUserId);

        if ($ctx->isCallback()) {
            $this->client->answerCallbackQuery(
                $ctx->rawUpdate['callback_query']['id'] ?? '',
                __('bot.blocked_user'),
                true
            );
        } else {
            $this->client->sendMessage($ctx->chatId, __('bot.blocked_user'));
        }

        return true;
    }

    private function router(): Router
    {
        return (new Router)
            ->register('role:*', RoleHandler::class)
            ->register('menu:*', MenuHandler::class)
            ->register('teacher:*', TeacherHandler::class)
            ->register('material:*', TeacherHandler::class)
            ->register('upload:*', TeacherHandler::class)
            ->register('student:*', StudentHandler::class)
            ->register('admin:*', AdminHandler::class)
            ->register('help:*', HelpHandler::class)
            ->register('confirm:*', MenuHandler::class)
            ->register('approve:*', MenuHandler::class);
    }
}
