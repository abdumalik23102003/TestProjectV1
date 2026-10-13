<?php

namespace App\Telegram\Handlers;

use App\Telegram\UpdateContext;
use App\Telegram\TelegramClient;
use App\Telegram\Menus;
use App\Telegram\Keyboard;
use Illuminate\Support\Facades\Log;

/**
 * Handles menu navigation callbacks
 * Routes: menu:main, menu:profile, menu:help, menu:approve_teachers, menu:users, menu:audit
 */
class MenuHandler
{
    public function handle(UpdateContext $ctx, TelegramClient $client, array $parts): void
    {
        $action = $parts[1] ?? 'main';

        // Must be authenticated
        if (!$ctx->user) {
            $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.unauthorized'));
            return;
        }

        match ($action) {
            'main' => $this->handleMain($ctx, $client),
            'profile' => $this->handleProfile($ctx, $client),
            'help' => $this->handleHelp($ctx, $client),
            'approve_teachers' => $this->handleApproveTeachers($ctx, $client),
            'users' => $this->handleUsers($ctx, $client),
            'audit' => $this->handleAudit($ctx, $client),
            default => $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.unknown_action')),
        };

        $client->answerCallbackQuery($this->getCallbackId($ctx), 'OK');
    }

    /**
     * Show main menu for user's role
     */
    protected function handleMain(UpdateContext $ctx, TelegramClient $client): void
    {
        $menu = Menus::main($ctx->user);

        $client->editMessageText(
            $ctx->chatId,
            $ctx->messageId,
            $menu['text'],
            $menu['buttons']
        );
    }

    /**
     * Show user profile
     */
    protected function handleProfile(UpdateContext $ctx, TelegramClient $client): void
    {
        $user = $ctx->user;
        $status = __("bot.status_{$user->status}");
        $role = __("bot.btn_" . ($user->role === 'admin' ? 'help' : $user->role));

        $profile = "👤 <b>Profil</b>\n\n";
        $profile .= "📝 <b>Ism:</b> {$user->full_name}\n";
        $profile .= "🆔 <b>ID:</b> {$user->telegram_user_id}\n";
        $profile .= "👥 <b>Rol:</b> {$role}\n";
        $profile .= "📊 <b>Status:</b> {$status}\n";

        if ($user->role === 'teacher') {
            $filesCount = \App\Models\Material::where('teacher_id', $user->id)->count();
            $profile .= "📚 <b>Fayllar:</b> {$filesCount} ta\n";
        } elseif ($user->role === 'student') {
            $teachersCount = $user->teachers()->count();
            $profile .= "👨‍🏫 <b>Ustozlar:</b> {$teachersCount} ta\n";
        }

        $buttons = [
            [Keyboard::button(__('bot.btn_back'), 'menu:main')],
        ];

        $client->editMessageText($ctx->chatId, $ctx->messageId, $profile, $buttons);
    }

    /**
     * Show help text
     */
    protected function handleHelp(UpdateContext $ctx, TelegramClient $client): void
    {
        $buttons = [
            [Keyboard::button(__('bot.btn_back'), 'menu:main')],
        ];

        $client->editMessageText(
            $ctx->chatId,
            $ctx->messageId,
            __('bot.help_text'),
            $buttons
        );
    }

    /**
     * Admin: List pending teachers (stub - calls AdminHandler)
     */
    protected function handleApproveTeachers(UpdateContext $ctx, TelegramClient $client): void
    {
        // This would be delegated to AdminHandler
        // For now, just show that menu is unavailable outside admin context
        if ($ctx->user->role !== 'admin') {
            $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.unauthorized'), true);
            return;
        }

        app(AdminHandler::class)->handle($ctx, $client, ['admin', 'approve_teachers']);
    }

    /**
     * Admin: List users (stub)
     */
    protected function handleUsers(UpdateContext $ctx, TelegramClient $client): void
    {
        if ($ctx->user->role !== 'admin') {
            $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.unauthorized'), true);
            return;
        }

        app(AdminHandler::class)->handle($ctx, $client, ['admin', 'users']);
    }

    /**
     * Admin: Show audit log (stub)
     */
    protected function handleAudit(UpdateContext $ctx, TelegramClient $client): void
    {
        if ($ctx->user->role !== 'admin') {
            $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.unauthorized'), true);
            return;
        }

        app(AdminHandler::class)->handle($ctx, $client, ['admin', 'audit']);
    }

    /**
     * Get callback query ID
     */
    protected function getCallbackId(UpdateContext $ctx): string
    {
        return $ctx->rawUpdate['callback_query']['id'] ?? '';
    }
}
