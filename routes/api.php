<?php

use App\Http\Controllers\TelegramWebhookController;
use App\Http\Middleware\VerifyTelegramSecret;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handleWebhook'])
    ->middleware(VerifyTelegramSecret::class);
