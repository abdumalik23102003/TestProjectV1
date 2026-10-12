<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramController;

Route::prefix('telegram')->group(function () {
    Route::post('/webhook', [TelegramController::class, 'handleUpdate']);
    Route::get('/webhook', [TelegramController::class, 'verifyWebhook']);
    Route::post('/set-webhook', [TelegramController::class, 'setWebhook']);
    Route::get('/webhook-info', [TelegramController::class, 'getWebhookInfo']);
});

// Deep-link route (optional web-based join)
Route::get('/join/{token}', function ($token) {
    return "Deep-link havolasi: /start join_{$token}";
})->name('telegram.join');
