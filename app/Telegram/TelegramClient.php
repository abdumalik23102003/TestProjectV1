<?php

namespace App\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramClient
{
    protected string $token;
    protected string $apiUrl = 'https://api.telegram.org';

    public function __construct()
    {
        $this->token = config('telegram.bot_token');
    }

    /**
     * Send text message with inline keyboard
     */
    public function sendMessage(int $chatId, string $text, array $inlineKeyboard = null): array
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($inlineKeyboard) {
            $payload['reply_markup'] = json_encode(
                ['inline_keyboard' => $inlineKeyboard],
                JSON_UNESCAPED_UNICODE
            );
        }

        return $this->request('sendMessage', $payload);
    }

    /**
     * Send document (file)
     */
    public function sendDocument(int $chatId, $file, string $filename, string $caption = null): array
    {
        $payload = [
            'chat_id' => $chatId,
        ];

        if ($caption) {
            $payload['caption'] = $caption;
            $payload['parse_mode'] = 'HTML';
        }

        return $this->requestWithFile('sendDocument', $payload, 'document', $file, $filename);
    }

    /**
     * Edit message reply markup (keyboard)
     */
    public function editMessageReplyMarkup(int $chatId, int $messageId, array $inlineKeyboard): array
    {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard]),
        ];

        return $this->request('editMessageReplyMarkup', $payload);
    }

    /**
     * Edit message text and keyboard
     */
    public function editMessageText(int $chatId, int $messageId, string $text, array $inlineKeyboard = null): array
    {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($inlineKeyboard) {
            $payload['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
        }

        return $this->request('editMessageText', $payload);
    }

    /**
     * Answer callback query
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): array
    {
        $payload = [
            'callback_query_id' => $callbackQueryId,
        ];

        if ($text) {
            $payload['text'] = $text;
            $payload['show_alert'] = $showAlert;
        }

        return $this->request('answerCallbackQuery', $payload);
    }

    /**
     * Get file info
     */
    public function getFile(string $fileId): array
    {
        return $this->request('getFile', ['file_id' => $fileId]);
    }

    /**
     * Download file content
     */
    public function downloadFile(string $filePath): ?string
    {
        $url = "{$this->apiUrl}/file/bot{$this->token}/{$filePath}";

        try {
            $response = Http::get($url);
            return $response->successful() ? $response->body() : null;
        } catch (\Exception $e) {
            Log::error('Telegram file download failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Set webhook
     */
    public function setWebhook(string $url, string $secretToken = null): array
    {
        $payload = ['url' => $url];

        if ($secretToken) {
            $payload['secret_token'] = $secretToken;
        }

        return $this->request('setWebhook', $payload);
    }

    /**
     * Get webhook info
     */
    public function getWebhookInfo(): array
    {
        return $this->request('getWebhookInfo');
    }

    /**
     * Get updates (polling)
     */
    public function getUpdates(int $offset = 0, int $limit = 100): array
    {
        return $this->request('getUpdates', [
            'offset' => $offset,
            'limit' => $limit,
            'timeout' => 30,
        ]);
    }

    /**
     * Internal: make request
     */
    protected function request(string $method, array $params = []): array
    {
        $url = "{$this->apiUrl}/bot{$this->token}/{$method}";

        try {
            $response = Http::post($url, $params);
            $body = $response->json();

            // Log errors
            if (!($body['ok'] ?? false)) {
                Log::warning("Telegram API error [{$method}]: " . ($body['description'] ?? 'Unknown'));
            }

            return $body ?? ['ok' => false];
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            // Mask token in logs
            $msg = str_replace($this->token, '***', $msg);
            Log::error("Telegram API failed [{$method}]: {$msg}");

            return ['ok' => false];
        }
    }

    /**
     * Internal: make request with file
     */
    protected function requestWithFile(string $method, array $params, string $fileParamName, $file, string $filename): array
    {
        $url = "{$this->apiUrl}/bot{$this->token}/{$method}";

        try {
            $response = Http::attach($fileParamName, $file, $filename)->post($url, $params);
            $body = $response->json();

            if (!($body['ok'] ?? false)) {
                Log::warning("Telegram API error [{$method}]: " . ($body['description'] ?? 'Unknown'));
            }

            return $body ?? ['ok' => false];
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $msg = str_replace($this->token, '***', $msg);
            Log::error("Telegram API failed [{$method}]: {$msg}");

            return ['ok' => false];
        }
    }
}
