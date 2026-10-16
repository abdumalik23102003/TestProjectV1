<?php

namespace App\Services;

use App\Telegram\TelegramClient;

/**
 * Controllerlar uchun yupqa qobiq. Barcha so'rovlar TelegramClient orqali ketadi:
 * u xatolarni loglaydi, tokenni maskalaydi va timeout o'rnatadi.
 */
class TelegramService
{
    protected TelegramClient $client;

    public function __construct(?TelegramClient $client = null)
    {
        $this->client = $client ?? new TelegramClient;
    }

    /**
     * $buttons — yoki tekis tugmalar ro'yxati (2 tadan qatorga bo'linadi),
     * yoki tayyor qatorlar ro'yxati [[btn, btn], [btn]].
     */
    public function sendMessage(int $chatId, string $text, array $buttons = []): array
    {
        $keyboard = $this->buildInlineKeyboard($buttons);

        return $this->client->sendMessage(
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
        if (is_array($first) && ! array_key_exists('text', $first)) {
            return array_values($buttons);
        }

        // Tekis ro'yxat: [btn, btn, btn] -> [[btn, btn], [btn]]
        return array_chunk(array_values($buttons), 2);
    }

    public function sendDocument(int $chatId, $file, string $filename = 'document'): array
    {
        return $this->client->sendDocument($chatId, $file, $filename);
    }

    public function editMessageReplyMarkup(int $chatId, int $messageId, array $buttons): array
    {
        return $this->client->editMessageReplyMarkup(
            $chatId,
            $messageId,
            $this->buildInlineKeyboard($buttons)
        );
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): array
    {
        return $this->client->answerCallbackQuery($callbackQueryId, $text, $showAlert);
    }
}
