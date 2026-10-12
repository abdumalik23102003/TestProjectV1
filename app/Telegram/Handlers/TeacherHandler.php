<?php

namespace App\Telegram\Handlers;

use App\Telegram\UpdateContext;
use App\Telegram\TelegramClient;
use App\Telegram\Keyboard;

class TeacherHandler
{
    public function handle(UpdateContext $ctx, TelegramClient $client, array $parts): void
    {
        $action = $parts[1] ?? null;

        match ($action) {
            'upload' => $this->upload($ctx, $client),
            'files' => $this->listFiles($ctx, $client),
            default => $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.unknown_action')),
        };
    }

    protected function upload(UpdateContext $ctx, TelegramClient $client): void
    {
        $client->sendMessage($ctx->chatId, __('bot.send_file'));
    }

    protected function listFiles(UpdateContext $ctx, TelegramClient $client): void
    {
        $client->sendMessage($ctx->chatId, __('bot.my_files', ['count' => 0, 'list' => __('bot.no_files')]));
    }

    protected function getCallbackId(UpdateContext $ctx): string
    {
        return $ctx->rawUpdate['callback_query']['id'] ?? '';
    }
}
