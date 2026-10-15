<?php

namespace Tests\Unit;

use App\Services\TelegramService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramServiceTest extends TestCase
{
    public function test_flat_buttons_are_split_into_rows_of_two(): void
    {
        $service = new TelegramService();

        $rows = $service->buildInlineKeyboard([
            ['text' => 'A', 'callback_data' => 'a'],
            ['text' => 'B', 'callback_data' => 'b'],
            ['text' => 'C', 'callback_data' => 'c'],
        ]);

        $this->assertCount(2, $rows);
        $this->assertCount(2, $rows[0]);
        $this->assertCount(1, $rows[1]);
        $this->assertSame('A', $rows[0][0]['text']);
    }

    public function test_prebuilt_rows_are_kept_as_is(): void
    {
        $service = new TelegramService();

        $rows = $service->buildInlineKeyboard([
            [['text' => 'A', 'callback_data' => 'a']],
            [['text' => 'B', 'callback_data' => 'b'], ['text' => 'C', 'callback_data' => 'c']],
        ]);

        $this->assertCount(2, $rows);
        $this->assertCount(1, $rows[0]);
        $this->assertCount(2, $rows[1]);
    }

    public function test_empty_buttons_give_empty_keyboard(): void
    {
        $this->assertSame([], (new TelegramService())->buildInlineKeyboard([]));
    }

    public function test_send_message_sends_valid_inline_keyboard(): void
    {
        config(['telegram.bot_token' => 'test-token']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        (new TelegramService())->sendMessage(123, 'Salom', [
            ['text' => 'A', 'callback_data' => 'a'],
            ['text' => 'B', 'callback_data' => 'b'],
            ['text' => 'C', 'callback_data' => 'c'],
        ]);

        Http::assertSent(function ($request) {
            $markup = json_decode($request['reply_markup'] ?? 'null', true);

            // inline_keyboard: qatorlar ro'yxati, har bir element — tugma ('text' kalitli).
            return str_contains($request->url(), '/sendMessage')
                && isset($markup['inline_keyboard'][0][0]['text'])
                && isset($markup['inline_keyboard'][1][0]['text']);
        });
    }

    public function test_send_message_without_buttons_has_no_reply_markup(): void
    {
        config(['telegram.bot_token' => 'test-token']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        (new TelegramService())->sendMessage(123, 'Salom');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendMessage')
                && !array_key_exists('reply_markup', $request->data());
        });
    }
}
