<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramService
{
    protected string $botToken;
    protected string $apiBaseUrl;

    public function __construct()
    {
        $this->botToken = config('telegram.bot_token');
        $this->apiBaseUrl = config('telegram.api_base_url');
    }

    public function sendMessage(int $chatId, string $text, array $buttons = []): array
    {
        $replyMarkup = [];

        if (!empty($buttons)) {
            $replyMarkup = ['inline_keyboard' => [array_chunk($buttons, 2)]];
        }

        $response = Http::post("{$this->apiBaseUrl}/bot{$this->botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => !empty($replyMarkup) ? json_encode($replyMarkup) : null,
        ]);

        return $response->json();
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
        $replyMarkup = ['inline_keyboard' => [array_chunk($buttons, 2)]];

        $response = Http::post("{$this->apiBaseUrl}/bot{$this->botToken}/editMessageReplyMarkup", [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => json_encode($replyMarkup),
        ]);

        return $response->json();
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
