<?php

namespace App\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramClient
{
    protected string $token;

    protected string $apiUrl = 'https://api.telegram.org';

    protected int $timeout = 30;

    public function __construct()
    {
        $this->token = (string) config('telegram.bot_token');
    }

    /**
     * Send text message with inline keyboard
     */
    public function sendMessage(int $chatId, string $text, ?array $inlineKeyboard = null): array
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
    public function sendDocument(int $chatId, $file, string $filename, ?string $caption = null): array
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
            'reply_markup' => json_encode(
                ['inline_keyboard' => $inlineKeyboard],
                JSON_UNESCAPED_UNICODE
            ),
        ];

        return $this->request('editMessageReplyMarkup', $payload);
    }

    /**
     * Edit message text and keyboard
     */
    public function editMessageText(int $chatId, int $messageId, string $text, ?array $inlineKeyboard = null): array
    {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($inlineKeyboard) {
            $payload['reply_markup'] = json_encode(
                ['inline_keyboard' => $inlineKeyboard],
                JSON_UNESCAPED_UNICODE
            );
        }

        return $this->request('editMessageText', $payload);
    }

    public function editOrSendMessage(
        int $chatId,
        ?int $messageId,
        string $text,
        ?array $inlineKeyboard = null
    ): array {
        if ($messageId && $messageId > 0) {
            $result = $this->editMessageText(
                $chatId,
                $messageId,
                $text,
                $inlineKeyboard
            );

            if ($result['ok'] ?? false) {
                return $result;
            }

            $description = strtolower($result['description'] ?? '');

            if (str_contains($description, 'message is not modified')) {
                return [
                    'ok' => true,
                    'result' => null,
                ];
            }
        }

        return $this->sendMessage(
            $chatId,
            $text,
            $inlineKeyboard
        );
    }

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
            $response = Http::timeout(60)->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable $e) {
            Log::error('Telegram file download failed: '.$this->mask($e->getMessage()));

            return null;
        }
    }

    /**
     * Set webhook
     */
    public function setWebhook(string $url, ?string $secretToken = null): array
    {
        $payload = ['url' => $url];

        if ($secretToken) {
            $payload['secret_token'] = $secretToken;
        }

        return $this->request('setWebhook', $payload);
    }

    /**
     * Delete webhook (polling rejimidan oldin kerak)
     */
    public function deleteWebhook(): array
    {
        return $this->request('deleteWebhook');
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
        ], 45);
    }

    /**
     * Internal: make request
     */
    protected function request(string $method, array $params = [], ?int $timeout = null): array
    {
        $url = "{$this->apiUrl}/bot{$this->token}/{$method}";

        try {
            $response = Http::timeout($timeout ?? $this->timeout)->post($url, $params);
            $body = $response->json();

            if (! ($body['ok'] ?? false)) {
                Log::warning("Telegram API error [{$method}]: ".($body['description'] ?? 'Unknown'));
            }

            return $body ?? ['ok' => false];
        } catch (\Throwable $e) {
            Log::error("Telegram API failed [{$method}]: ".$this->mask($e->getMessage()));

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
            $response = Http::timeout(120)
                ->attach($fileParamName, $file, $filename)
                ->post($url, $params);
            $body = $response->json();

            if (! ($body['ok'] ?? false)) {
                Log::warning("Telegram API error [{$method}]: ".($body['description'] ?? 'Unknown'));
            }

            return $body ?? ['ok' => false];
        } catch (\Throwable $e) {
            Log::error("Telegram API failed [{$method}]: ".$this->mask($e->getMessage()));

            return ['ok' => false];
        }
    }

    /**
     * Tokenni matndan yashiradi (loglar va xato xabarlari uchun).
     */
    protected function mask(string $message): string
    {
        if ($this->token === '') {
            return $message;
        }

        return str_replace($this->token, '***', $message);
    }
}
