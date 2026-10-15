<?php

namespace App\Services;

use App\Telegram\TelegramClient;
use Illuminate\Support\Facades\Http;

class TelegramService
{
    protected string $botToken;
    protected string $apiBaseUrl;

    public function __construct()
    {
        $this->botToken = config('telegram.bot_token');
        $this->apiBaseUrl = config('telegram.api_url');
    }

    /**
     * Xabar yuborish.
     *
     * $buttons — yoki tekis tugmalar ro'yxati (2 tadan qatorga bo'linadi),
     * yoki tayyor qatorlar ro'yxati [[btn, btn], [btn]].
     *
     * Barcha so'rovlar TelegramClient orqali ketadi: u Telegram xatolarini
     * logga yozadi va tokenni maskalaydi.
     */
    public function sendMessage(int $chatId, string $text, array $buttons = []): array
    {
        $keyboard = $this->buildInlineKeyboard($buttons);

        return (new TelegramClient())->sendMessage(
            $chatId,
            $text,
            $keyboard !== [] ? $keyboard : null
        );
    }

    /**
     * Tugmalarni Telegram inline_keyboard formatiga (qatorlar ro'yxati) keltiradi.
     */
    public function buildInlineKeyboard(array $buttons): array
    {
        if ($buttons === []) {
            return [];
        }

        $first = reset($buttons);

        // Allaqachon qatorlarga bo'lingan: [[btn, btn], [btn]]
        if (is_array($first) && !array_key_exists('text', $first)) {
            return array_values($buttons);
        }

        // Tekis ro'yxat: [btn, btn, btn] -> [[btn, btn], [btn]]
        return array_chunk(array_values($buttons), 2);
    }

    public function sendDocument(int $chatId, $file, string $filename = 'document'): array
    {
        $response = Http::attach('document', $file, $filename)
            ->post("{$this->apiBaseUrl}/bot{$this->botToken}/sendDocument", [
                'chat_id' => $chatId,
            ]);

        return $response->json();
    }

    public function editMessageReplyMarkup(int $chatId, int $messageId, array $buttons): array
    {
        return (new TelegramClient())->editMessageReplyMarkup(
            $chatId,
            $messageId,
            $this->buildInlineKeyboard($buttons)
        );
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): array
    {
        $response = Http::post("{$this->apiBaseUrl}/bot{$this->botToken}/answerCallbackQuery", [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ]);

        return $response->json();
    }

    public function getFile(string $fileId): array
    {
        $response = Http::get("{$this->apiBaseUrl}/bot{$this->botToken}/getFile", [
            'file_id' => $fileId,
        ]);

        return $response->json();
    }

    public function downloadFile(string $filePath): string
    {
        $response = Http::get("{$this->apiBaseUrl}/file/bot{$this->botToken}/{$filePath}");

        return $response->body();
    }

    public function setWebhook(string $url): array
    {
        $response = Http::post("{$this->apiBaseUrl}/bot{$this->botToken}/setWebhook", [
            'url' => $url,
        ]);

        return $response->json();
    }

    public function deleteWebhook(): array
    {
        $response = Http::post("{$this->apiBaseUrl}/bot{$this->botToken}/deleteWebhook");

        return $response->json();
    }

    public function getWebhookInfo(): array
    {
        $response = Http::get("{$this->apiBaseUrl}/bot{$this->botToken}/getWebhookInfo");

        return $response->json();
    }
}
