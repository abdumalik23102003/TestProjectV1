<?php

namespace Tests\Unit;

use App\Telegram\TelegramClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TelegramClientTest extends TestCase
{
    public function test_send_message_with_keyboard(): void
    {
        Http::fake();
        $client = new TelegramClient;

        $kb = [
            [['text' => 'Button', 'callback_data' => 'action:data']],
        ];

        $client->sendMessage(123, 'Test', $kb);

        Http::assertSent(function ($request) {
            return str_contains((string) $request->body(), 'inline_keyboard');
        });
    }

    public function test_send_message_without_keyboard(): void
    {
        Http::fake();
        $client = new TelegramClient;

        $client->sendMessage(123, 'Test');

        Http::assertSent(function ($request) {
            return ! str_contains((string) $request->body(), 'reply_markup');
        });
    }

    public function test_token_masked_in_logs(): void
    {
        config(['telegram.bot_token' => 'secret_token_123']);

        Http::fake(function () {
            throw new \Exception('Test error with secret_token_123 in it');
        });

        Log::spy();

        (new TelegramClient)->getWebhookInfo();

        Log::shouldHaveReceived('error')
            ->withArgs(function ($message, $context = []) {
                $dump = $message.json_encode($context);

                return ! str_contains($dump, 'secret_token_123') && str_contains($dump, '***');
            })
            ->atLeast()->once();
    }
}
