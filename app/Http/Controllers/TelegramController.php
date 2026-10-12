<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Services\TelegramService;
use App\Jobs\ProcessTelegramUpdate;

class TelegramController extends Controller
{
    protected TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    public function handleUpdate(Request $request): \Illuminate\Http\JsonResponse
    {
        $update = $request->json()->all();

        // Queue orqali qayta ishlash
        ProcessTelegramUpdate::dispatch($update);

        return response()->json(['ok' => true]);
    }

    public function verifyWebhook(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function setWebhook(): \Illuminate\Http\JsonResponse
    {
        $url = config('telegram.webhook_url');
        $result = $this->telegramService->setWebhook($url);

        return response()->json($result);
    }

    public function getWebhookInfo(): \Illuminate\Http\JsonResponse
    {
        $info = $this->telegramService->getWebhookInfo();

        return response()->json($info);
    }
}
