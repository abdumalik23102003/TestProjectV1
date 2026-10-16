<?php

namespace App\Console\Commands;

use App\Telegram\TelegramClient;
use Illuminate\Console\Command;

class TelegramWebhookInfoCommand extends Command
{
    protected $signature = 'telegram:webhook-info';

    protected $description = 'Get Telegram webhook info';

    public function handle(): int
    {
        $client = new TelegramClient;
        $result = $client->getWebhookInfo();

        if ($result['ok'] ?? false) {
            $this->info('Webhook Info:');
            $info = $result['result'] ?? [];
            foreach ($info as $key => $val) {
                $this->line("  {$key}: ".json_encode($val));
            }

            return 0;
        }

        $this->error('Failed: '.($result['description'] ?? 'Unknown error'));

        return 1;
    }
}
