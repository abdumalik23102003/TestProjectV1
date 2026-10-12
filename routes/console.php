<?php

use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    \App\Models\BotSession::where('expires_at', '<', now())->delete();
})->everyMinute();

Schedule::call(function () {
    \App\Models\MaterialVersion::where('status', 'retained')
        ->where('retain_until', '<', now())
        ->delete();
})->daily();

Schedule::call(function () {
    \App\Models\Material::where('status', 'archived')
        ->where('updated_at', '<', now()->subDays(30))
        ->delete();
})->daily();
