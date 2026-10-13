<?php

namespace App\Telegram\Handlers;

use App\Telegram\UpdateContext;
use App\Telegram\TelegramClient;
use App\Services\SessionService;

class StudentHandler
{
    public function handle(UpdateContext $ctx, TelegramClient $client, array $parts): void
    {
        $action = $parts[1] ?? null;

        match ($action) {
            'search' => $this->search($ctx, $client),
            'teachers' => $this->listTeachers($ctx, $client),
            'recent' => $this->listRecent($ctx, $client),
            'profile' => $this->showProfile($ctx, $client),
            default => $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.unknown_action')),
        };
    }

    protected function search(UpdateContext $ctx, TelegramClient $client): void
    {
        SessionService::startWizard(
            $ctx->telegramUserId,
            'search',
            'enter_query'
        );

        $client->sendMessage(
            $ctx->chatId,
            __('bot.search_prompt')
        );
    }

    protected function listTeachers(UpdateContext $ctx, TelegramClient $client): void
    {
        $client->sendMessage($ctx->chatId, __('bot.no_teachers'));
    }

    protected function listRecent(UpdateContext $ctx, TelegramClient $client): void
    {
        $client->sendMessage($ctx->chatId, __('bot.no_results'));
    }

    protected function showProfile(UpdateContext $ctx, TelegramClient $client): void
    {
        if ($ctx->user) {
            $client->sendMessage($ctx->chatId, "👤 <b>{$ctx->user->full_name}</b>\n🎓 {$ctx->user->role}");
        }
    }

    protected function getCallbackId(UpdateContext $ctx): string
    {
        return $ctx->rawUpdate['callback_query']['id'] ?? '';
    }
}
