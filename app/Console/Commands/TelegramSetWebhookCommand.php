<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Telegram\TelegramClient;

class TelegramSetWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook';
    protected $description = 'Set Telegram webhook URL';

    public function handle(): int
    {
        $client = new TelegramClient();
        $url = config('telegram.webhook_url');
        $secret = config('telegram.webhook_secret');

        $this->info("Setting webhook to: {$url}");

        $result = $client->setWebhook($url, $secret);

        if ($result['ok'] ?? false) {
            $this->info('✓ Webhook set successfully');
            return 0;
        }

        $this->error('✗ Failed: ' . ($result['description'] ?? 'Unknown error'));
        return 1;
    }
}
