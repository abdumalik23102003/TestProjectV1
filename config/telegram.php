<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
    'bot_username' => env('TELEGRAM_BOT_USERNAME', ''),
    'webhook_url' => env('TELEGRAM_WEBHOOK_URL', 'https://example.com/api/telegram/webhook'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),
    'admin_telegram_ids' => env('ADMIN_TELEGRAM_IDS', ''),
    'api_mode' => env('TELEGRAM_API_MODE', 'remote'),
    'api_url' => 'https://api.telegram.org',
];
