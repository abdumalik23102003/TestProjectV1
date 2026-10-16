<?php

namespace App\Console\Commands;

use App\Telegram\TelegramClient;
use App\Telegram\UpdateContext;
use App\Telegram\UpdateDispatcher;
use Illuminate\Console\Command;

class TelegramPollCommand extends Command
{
    protected $signature = 'telegram:poll';

    protected $description = 'Poll Telegram for updates (development only)';

    public function handle(): int
    {
        if (config('app.env') !== 'local') {
            $this->error('This command is for development only!');

            return 1;
        }

        $this->info('Polling for updates (Ctrl+C to stop)...');

        $client = new TelegramClient;
        $offset = 0;
        $dispatcher = app(UpdateDispatcher::class);

        while (true) {
            $result = $client->getUpdates($offset);

            if ($result['ok'] ?? false) {
                $updates = $result['result'] ?? [];

                foreach ($updates as $update) {
                    $this->line("Update {$update['update_id']}: ".json_encode($update));

                    // Process
                    try {
                        $ctx = new UpdateContext($update);
                        $dispatcher->dispatch($ctx);
                    } catch (\Throwable $e) {
                        $this->error('Error: '.$e->getMessage());
                    }

                    $offset = $update['update_id'] + 1;
                }
            } else {
                $this->error('Poll error: '.($result['description'] ?? 'Unknown'));
            }

            sleep(1);
        }

        return 0;
    }
}
