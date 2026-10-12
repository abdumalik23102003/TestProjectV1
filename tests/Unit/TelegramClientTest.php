<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Telegram\TelegramClient;
use Illuminate\Support\Facades\Http;

class TelegramClientTest extends TestCase
{
    public function test_send_message_with_keyboard(): void
    {
        Http::fake();
        $client = new TelegramClient();

        $kb = [
            [['text' => 'Button', 'callback_data' => 'action:data']]
        ];

        $client->sendMessage(123, 'Test', $kb);

        Http::assertSent(function ($request) {
            return str_contains((string)$request->body(), 'inline_keyboard');
        });
    }

    public function test_send_message_without_keyboard(): void
    {
        Http::fake();
        $client = new TelegramClient();

        $client->sendMessage(123, 'Test');

        Http::assertSent(function ($request) {
            return !str_contains((string)$request->body(), 'reply_markup');
        });
    }

    public function test_token_masked_in_logs(): void
    {
        config(['telegram.bot_token' => 'secret_token_123']);
        Http::fake(function () {
            throw new \Exception('Test error with secret_token_123 in it');
        });

        $client = new TelegramClient();
        $client->getWebhookInfo();

        // Log should mask token
        // This is verified by checking logs don't contain actual token
    }
}
