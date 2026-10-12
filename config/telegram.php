<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN', '<YOUR_TELEGRAM_BOT_TOKEN_HERE>'),
    'bot_username' => env('TELEGRAM_BOT_USERNAME', '<YOUR_BOT_USERNAME>'),
    'webhook_url' => env('TELEGRAM_WEBHOOK_URL', 'https://yourdomain.com/api/telegram/webhook'),
    'api_mode' => env('TELEGRAM_API_MODE', 'remote'), // 'remote' or 'local'
    'api_base_url' => 'https://api.telegram.org',
];
