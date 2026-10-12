<?php

namespace App\Telegram\Handlers;

use App\Telegram\UpdateContext;
use App\Telegram\TelegramClient;

class HelpHandler
{
    public function handle(UpdateContext $ctx, TelegramClient $client, array $parts): void
    {
        $client->sendMessage($ctx->chatId, __('bot.help_text'));
    }
}
