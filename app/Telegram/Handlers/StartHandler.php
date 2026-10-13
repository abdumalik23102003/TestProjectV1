<?php

namespace App\Telegram\Handlers;

use App\Telegram\UpdateContext;
use App\Telegram\TelegramClient;
use App\Telegram\Keyboard;
use App\Models\User;
use App\Helpers\HtmlHelper;

class StartHandler
{
    public function handle(UpdateContext $ctx, TelegramClient $client, array $parts): void
    {
        // Private chat only
        if ($ctx->chatId != $ctx->telegramUserId) {
            return;
        }

        $user = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        // Check for deep-link
        $deepLink = $parts[2] ?? null;

        if ($user && $deepLink && str_starts_with($deepLink, 'join_')) {
            $this->handleJoinLink($ctx, $client, $user, $deepLink);
            return;
        }

        // New user or existing
        if (!$user) {
            $this->showRoleSelection($ctx, $client);
        } else {
            // Existing user - show appropriate menu
            if ($user->status === 'blocked') {
                $client->sendMessage($ctx->chatId, __('bot.blocked_user'));
                return;
            }

            if ($user->status === 'pending') {
                if ($user->role === 'teacher') {
                    $client->sendMessage($ctx->chatId, __('bot.waiting_approval'));
                } else {
                    $client->sendMessage($ctx->chatId, __('bot.pending_student'));
                }
                return;
            }

            if ($user->status === 'rejected') {
                $client->sendMessage($ctx->chatId, __('bot.rejected'));
                return;
            }

            // Show menu
            $this->showMenu($ctx, $client, $user);
        }
    }

    protected function showRoleSelection(UpdateContext $ctx, TelegramClient $client): void
    {
        $kb = [
            [Keyboard::button(__('bot.btn_teacher'), 'role:teacher')],
            [Keyboard::button(__('bot.btn_student'), 'role:student')],
            [Keyboard::button(__('bot.btn_help'), 'help:index')],
        ];

        $client->sendMessage(
            $ctx->chatId,
            __('bot.choose_role'),
            $kb
        );
    }

    public function showMenu(UpdateContext $ctx, TelegramClient $client, User $user): void
    {
        $text = match ($user->role) {
            'admin' => __('bot.admin_menu'),
            'teacher' => __('bot.teacher_menu'),
            default => __('bot.student_menu'),
        };

        $kb = match ($user->role) {
            'admin' => [
                [Keyboard::button(__('bot.btn_approve_teachers'), 'admin:approve_teachers')],
                [Keyboard::button(__('bot.btn_users'), 'admin:users')],
                [Keyboard::button(__('bot.btn_audit'), 'admin:audit')],
            ],
            'teacher' => [
                [Keyboard::button(__('bot.btn_upload'), 'teacher:upload')],
                [Keyboard::button(__('bot.btn_my_files'), 'teacher:files')],
                [Keyboard::button(__('bot.btn_search'), 'student:search')],
            ],
            default => [
                [Keyboard::button(__('bot.btn_search'), 'student:search')],
                [Keyboard::button(__('bot.btn_my_teachers'), 'student:teachers')],
                [Keyboard::button(__('bot.btn_recent'), 'student:recent')],
                [Keyboard::button(__('bot.btn_profile'), 'student:profile')],
            ],
        };

        $client->sendMessage($ctx->chatId, $text, $kb);
    }

    protected function handleJoinLink(UpdateContext $ctx, TelegramClient $client, User $user, string $deepLink): void
    {
        // Deep-link join is handled separately - for now, just show message
        $client->sendMessage($ctx->chatId, "🔗 Join link detected: " . substr($deepLink, 5));
    }
}
