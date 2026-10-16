<?php

use App\Models\BotSession;
use Illuminate\Support\Facades\Schedule;

// Muddati tugagan wizard sessiyalari
Schedule::call(fn () => BotSession::where('expires_at', '<', now())->delete())
    ->name('bot-sessions-cleanup')
    ->everyMinute();

// Yetim fayllar va eskirgan arxiv
Schedule::command('materials:cleanup')->hourly();
