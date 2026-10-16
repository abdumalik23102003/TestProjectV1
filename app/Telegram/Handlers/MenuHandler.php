<?php

namespace App\Telegram\Handlers;

use App\Models\Material;
use App\Services\InviteService;
use App\Telegram\Keyboard;
use App\Telegram\Menus;
use App\Telegram\TelegramClient;
use App\Telegram\UpdateContext;

/**
 * Menyu navigatsiyasi: menu:main, menu:profile, menu:help,
 * menu:approve_teachers, menu:users, menu:audit
 */
class MenuHandler
{
    public function handle(UpdateContext $ctx, TelegramClient $client, array $parts): void
    {
        $action = $parts[1] ?? 'main';
        $callbackId = $this->getCallbackId($ctx);

        if (! $ctx->user) {
            $client->answerCallbackQuery($callbackId, __('bot.unauthorized'), true);

            return;
        }

        if ($ctx->user->status === 'blocked') {
            $client->answerCallbackQuery($callbackId, __('bot.blocked_user'), true);

            return;
        }

        match ($action) {
            'main' => $this->handleMain($ctx, $client),
            'profile' => $this->handleProfile($ctx, $client),
            'help' => $this->handleHelp($ctx, $client),
            'approve_teachers' => $this->delegateToAdmin($ctx, $client, 'approve_teachers'),
            'users' => $this->delegateToAdmin($ctx, $client, 'users'),
            'audit' => $this->delegateToAdmin($ctx, $client, 'audit'),
            default => $client->answerCallbackQuery($callbackId, __('bot.unknown_action')),
        };

        $client->answerCallbackQuery($callbackId);
    }

    protected function handleMain(UpdateContext $ctx, TelegramClient $client): void
    {
        $menu = Menus::main($ctx->user);

        $client->editOrSendMessage(
            $ctx->chatId,
            $ctx->messageId,
            $menu['text'],
            $menu['buttons']
        );
    }

    protected function handleProfile(UpdateContext $ctx, TelegramClient $client): void
    {
        $user = $ctx->user;
        $status = __("bot.status_{$user->status}");

        $role = match ($user->role) {
            'teacher' => 'Ustoz',
            'admin' => 'Admin',
            default => 'O‘quvchi',
        };

        $profile = "👤 <b>Profil</b>\n\n";
        $profile .= '📝 <b>Ism:</b> '.e($user->full_name, false)."\n";
        $profile .= "🆔 <b>ID:</b> {$user->telegram_user_id}\n";
        $profile .= "👥 <b>Rol:</b> {$role}\n";
        $profile .= "📊 <b>Status:</b> {$status}\n";

        if ($user->role === 'teacher') {
            $filesCount = Material::where('teacher_id', $user->id)->count();
            $profile .= "📚 <b>Fayllar:</b> {$filesCount} ta\n";
        } elseif ($user->role === 'student') {
            $teachersCount = app(InviteService::class)->getTeacherConnectionsCount($user);
            $profile .= "👨‍🏫 <b>Ustozlar:</b> {$teachersCount} ta\n";
        }

        $client->editOrSendMessage(
            $ctx->chatId,
            $ctx->messageId,
            $profile,
            [[Keyboard::button(__('bot.btn_back'), 'menu:main')]]
        );
    }

    protected function handleHelp(UpdateContext $ctx, TelegramClient $client): void
    {
        $client->editOrSendMessage(
            $ctx->chatId,
            $ctx->messageId,
            __('bot.help_text'),
            [[Keyboard::button(__('bot.btn_back'), 'menu:main')]]
        );
    }

    /**
     * Admin amallari AdminHandler'ga uzatiladi (u rolni o'zi ham tekshiradi).
     */
    protected function delegateToAdmin(UpdateContext $ctx, TelegramClient $client, string $action): void
    {
        if ($ctx->user->role !== 'admin') {
            $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.unauthorized'), true);

            return;
        }

        app(AdminHandler::class)->handle($ctx, $client, ['admin', $action]);
    }

    protected function getCallbackId(UpdateContext $ctx): string
    {
        return $ctx->rawUpdate['callback_query']['id'] ?? '';
    }
}
