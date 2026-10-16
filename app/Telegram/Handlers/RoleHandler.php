<?php

namespace App\Telegram\Handlers;

use App\Services\AuditService;
use App\Services\RegistrationService;
use App\Services\SessionService;
use App\Telegram\TelegramClient;
use App\Telegram\UpdateContext;

class RoleHandler
{
    public function handle(UpdateContext $ctx, TelegramClient $client, array $parts): void
    {
        $callbackId = $this->getCallbackId($ctx);
        $role = $parts[1] ?? null;

        if (! in_array($role, ['teacher', 'student'], true)) {
            $client->answerCallbackQuery($callbackId, __('bot.invalid_input'));

            return;
        }

        // Faqat ro'yxatdan o'tmagan foydalanuvchi rol tanlay oladi.
        if (! app(RegistrationService::class)->needsRegistration($ctx->user)) {
            $client->answerCallbackQuery($callbackId, __('bot.already_registered'), true);

            return;
        }

        // Deep-link orqali kelgan taklif tokenini yo'qotmaymiz.
        $existing = SessionService::getSession($ctx->telegramUserId, 'registration') ?? [];
        $data = ['role' => $role];

        if (! empty($existing['join_token'])) {
            $data['join_token'] = $existing['join_token'];
        }

        SessionService::startWizard($ctx->telegramUserId, 'registration', 'enter_name', $data);

        app(AuditService::class)->log('role_selected', 'registration', null, null, [
            'role' => $role,
            'telegram_user_id' => $ctx->telegramUserId,
        ]);

        $client->answerCallbackQuery($callbackId);
        $client->sendMessage($ctx->chatId, __('bot.enter_name'));
    }

    protected function getCallbackId(UpdateContext $ctx): string
    {
        return $ctx->rawUpdate['callback_query']['id'] ?? '';
    }
}
