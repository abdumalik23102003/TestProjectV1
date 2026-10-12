<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramWebhookController;

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handleWebhook'])
    ->middleware([\App\Http\Middleware\VerifyTelegramSecret::class]);

Route::post('/telegram/set-webhook', [TelegramWebhookController::class, 'setWebhook']);
Route::get('/telegram/webhook-info', [TelegramWebhookController::class, 'getWebhookInfo']);
