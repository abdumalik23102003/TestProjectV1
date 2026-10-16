<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    public function test_webhook_without_secret(): void
    {
        Http::fake();

        $response = $this->postJson('/api/telegram/webhook', [
            'update_id' => 1,
            'message' => ['chat' => ['id' => 1], 'from' => ['id' => 1], 'text' => '/start'],
        ]);

        $response->assertStatus(200);
    }

    public function test_webhook_with_wrong_secret(): void
    {
        config(['telegram.webhook_secret' => 'secret123']);
        Http::fake();

        $response = $this->postJson('/api/telegram/webhook', [
            'update_id' => 1,
            'message' => ['chat' => ['id' => 1], 'from' => ['id' => 1], 'text' => '/start'],
        ]);

        $response->assertStatus(403);
    }

    public function test_webhook_with_correct_secret(): void
    {
        config(['telegram.webhook_secret' => 'secret123']);
        Http::fake();

        $response = $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'secret123',
        ])->postJson('/api/telegram/webhook', [
            'update_id' => 1,
            'message' => ['chat' => ['id' => 1], 'from' => ['id' => 1], 'text' => '/start'],
        ]);

        $response->assertStatus(200);
    }

    public function test_webhook_deduplicates_update_id(): void
    {
        Http::fake();

        $update = ['update_id' => 999, 'message' => ['chat' => ['id' => 1], 'from' => ['id' => 1], 'text' => '/start']];

        $response1 = $this->postJson('/api/telegram/webhook', $update);
        $response2 = $this->postJson('/api/telegram/webhook', $update);

        $response1->assertStatus(200);
        $response2->assertStatus(200);
    }
}
