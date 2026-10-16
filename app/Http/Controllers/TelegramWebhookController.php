<?php

namespace App\Http\Controllers;

use App\Telegram\TelegramClient;
use App\Telegram\UpdateContext;
use App\Telegram\UpdateDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramWebhookController extends Controller
{
    public function handleWebhook(Request $request): Response
    {
        $update = $request->json()->all();

        if (empty($update)) {
            Log::warning('Empty webhook update');

            return response('', 200);
        }

        $updateId = $update['update_id'] ?? null;

        if ($updateId === null) {
            return response('', 200);
        }

        // Atomik: ikki bir vaqtdagi so'rovdan faqat bittasi o'tadi.
        if (! Cache::add("telegram_update_{$updateId}", true, now()->addDay())) {
            Log::info("Webhook: duplicate update_id [{$updateId}]");

            return response('', 200);
        }

        try {
            app(UpdateDispatcher::class)->dispatch(new UpdateContext($update));
        } catch (Throwable $e) {
            Log::error('Webhook processing error', [
                'update_id' => $updateId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->notifyProcessingFailure($update);
        }

        return response('', 200);
    }

    /**
     * Kutilmagan xatoda foydalanuvchi jim qolmasligi uchun unga qisqa xabar yuboramiz
     * (callback bo'lsa, tugma "aylanishini" ham to'xtatamiz).
     */
    private function notifyProcessingFailure(array $update): void
    {
        try {
            $client = new TelegramClient;

            $chatId = $update['message']['chat']['id']
                ?? $update['callback_query']['message']['chat']['id']
                ?? null;

            $callbackId = $update['callback_query']['id'] ?? null;

            if ($callbackId !== null) {
                $client->answerCallbackQuery($callbackId);
            }

            if ($chatId !== null) {
                $client->sendMessage((int) $chatId, __('bot.unknown_error'));
            }
        } catch (Throwable $e) {
            Log::error('Webhook failure notice failed', ['error' => $e->getMessage()]);
        }
    }
}
