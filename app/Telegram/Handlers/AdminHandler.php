<?php

namespace App\Telegram\Handlers;

use App\Telegram\UpdateContext;
use App\Telegram\TelegramClient;
use App\Telegram\Keyboard;

class AdminHandler
{
    public function handle(UpdateContext $ctx, TelegramClient $client, array $parts): void
    {
        $action = $parts[1] ?? null;

        match ($action) {
            'approve_teachers' => $this->approvePendingTeachers($ctx, $client),
            'users' => $this->listUsers($ctx, $client),
            'audit' => $this->viewAuditLog($ctx, $client),
            default => $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.unknown_action')),
        };
    }

    protected function approvePendingTeachers(UpdateContext $ctx, TelegramClient $client): void
    {
        $pending = \App\Models\User::where('role', 'teacher')
            ->where('status', 'pending')
            ->get();

        if ($pending->isEmpty()) {
            $client->sendMessage($ctx->chatId, __('bot.no_pending'));
            return;
        }

        $text = __('bot.pending_teachers', ['count' => count($pending), 'list' => '...']);
        $client->sendMessage($ctx->chatId, $text);
    }

    protected function listUsers(UpdateContext $ctx, TelegramClient $client): void
    {
        $client->sendMessage($ctx->chatId, "👥 Foydalanuvchilar...");
    }

    protected function viewAuditLog(UpdateContext $ctx, TelegramClient $client): void
    {
        $logs = \App\Models\AuditLog::latest()->limit(10)->get();
        $text = __('bot.audit_log', ['logs' => json_encode($logs->toArray())]);
        $client->sendMessage($ctx->chatId, $text);
    }

    protected function getCallbackId(UpdateContext $ctx): string
    {
        return $ctx->rawUpdate['callback_query']['id'] ?? '';
    }
}
