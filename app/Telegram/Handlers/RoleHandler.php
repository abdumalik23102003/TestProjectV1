<?php

namespace App\Telegram\Handlers;

use App\Telegram\UpdateContext;
use App\Telegram\TelegramClient;
use App\Telegram\Keyboard;
use App\Models\User;
use App\Helpers\HtmlHelper;
use App\Services\SessionService;

class RoleHandler
{
    public function handle(UpdateContext $ctx, TelegramClient $client, array $parts): void
    {
        $role = $parts[1] ?? null;

        if (!in_array($role, ['teacher', 'student'])) {
            $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.invalid_input'));
            return;
        }

        // Only new users can select role
        if ($ctx->user && $ctx->user->role !== 'student') {
            $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.already_registered'));
            return;
        }

        // Save to session
        SessionService::startWizard($ctx->telegramUserId, 'registration', 'enter_name', ['role' => $role]);

        $client->answerCallbackQuery($this->getCallbackId($ctx), 'OK');
        $client->sendMessage($ctx->chatId, __('bot.enter_name'));
    }

    protected function getCallbackId(UpdateContext $ctx): string
    {
        return $ctx->rawUpdate['callback_query']['id'] ?? '';
    }
}
