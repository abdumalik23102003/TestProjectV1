<?php

namespace App\Telegram\Handlers;

use App\Telegram\TelegramClient;
use App\Telegram\UpdateContext;

class HelpHandler
{
    public function handle(UpdateContext $ctx, TelegramClient $client, array $parts): void
    {
        $client->sendMessage($ctx->chatId, __('bot.help_text'));
    }
}
