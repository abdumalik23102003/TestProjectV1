<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use App\Services\ConnectionService;
use App\Services\RegistrationService;
use App\Services\SessionService;
use App\Telegram\Keyboard;
use App\Telegram\Menus;
use App\Telegram\TelegramClient;
use App\Telegram\UpdateContext;

class StartHandler
{
    public function handle(UpdateContext $ctx, TelegramClient $client, array $parts): void
    {
        // Faqat shaxsiy chatda ishlaydi.
        if ($ctx->chatId != $ctx->telegramUserId) {
            return;
        }

        $user = $ctx->user;
        $param = trim((string) ($parts[2] ?? ''));
        $joinToken = str_starts_with($param, 'join_') ? substr($param, 5) : null;

        // Yangi yoki ro'yxatdan o'tishni yarim qoldirgan foydalanuvchi.
        if (app(RegistrationService::class)->needsRegistration($user)) {
            $this->startRegistration($ctx, $client, $joinToken);

            return;
        }

        if ($user->status === 'blocked') {
            $client->sendMessage($ctx->chatId, __('bot.blocked_user'));

            return;
        }

        if ($user->status === 'pending') {
            $client->sendMessage(
                $ctx->chatId,
                $user->role === 'teacher' ? __('bot.waiting_approval') : __('bot.pending_student')
            );

            return;
        }

        if ($user->status === 'rejected') {
            $client->sendMessage($ctx->chatId, __('bot.rejected'));

            return;
        }

        if ($joinToken !== null) {
            $this->handleJoin($ctx, $client, $user, $joinToken);

            return;
        }

        $this->showMenu($ctx, $client, $user);
    }

    protected function startRegistration(UpdateContext $ctx, TelegramClient $client, ?string $joinToken): void
    {
        SessionService::startWizard(
            $ctx->telegramUserId,
            'registration',
            'choose_role',
            $joinToken !== null ? ['join_token' => $joinToken] : []
        );

        $text = __('bot.choose_role');

        if ($joinToken !== null) {
            $text = __('bot.join_register_first')."\n\n".$text;
        }

        $client->sendMessage($ctx->chatId, $text, [
            [Keyboard::button(__('bot.btn_teacher'), 'role:teacher')],
            [Keyboard::button(__('bot.btn_student'), 'role:student')],
            [Keyboard::button(__('bot.btn_help'), 'help:index')],
        ]);
    }

    protected function handleJoin(UpdateContext $ctx, TelegramClient $client, User $user, string $token): void
    {
        $result = app(ConnectionService::class)->requestJoin($user, $token);

        $client->sendMessage(
            $ctx->chatId,
            __(ConnectionService::joinMessageKey($result['status'])),
            [[Keyboard::button(__('bot.btn_back'), 'menu:main')]]
        );
    }

    public function showMenu(UpdateContext $ctx, TelegramClient $client, User $user): void
    {
        $menu = Menus::main($user);

        $client->sendMessage($ctx->chatId, $menu['text'], $menu['buttons']);
    }
}
