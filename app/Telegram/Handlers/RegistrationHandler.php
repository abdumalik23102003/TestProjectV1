<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use App\Services\AuditService;
use App\Services\ConnectionService;
use App\Services\RegistrationService;
use App\Services\SessionService;
use App\Telegram\Keyboard;
use App\Telegram\Menus;
use App\Telegram\TelegramClient;
use App\Telegram\UpdateContext;

class RegistrationHandler
{
    public function handleName(UpdateContext $ctx, TelegramClient $client): void
    {
        $registration = app(RegistrationService::class);

        $session = SessionService::getSession($ctx->telegramUserId, 'registration');

        if (! $session || ($session['current_step'] ?? null) !== 'enter_name') {
            return;
        }

        // Allaqachon ro'yxatdan o'tgan (yoki bloklangan) foydalanuvchi qayta kira olmaydi.
        $existing = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        if (! $registration->needsRegistration($existing)) {
            SessionService::clearSession($ctx->telegramUserId, 'registration');
            $client->sendMessage($ctx->chatId, __('bot.already_registered'));

            return;
        }

        $name = $registration->normalizeName((string) $ctx->messageText);

        if (! $registration->isValidName($name)) {
            $client->sendMessage($ctx->chatId, __('bot.invalid_name_rules'));

            return;
        }

        $role = in_array($session['role'] ?? null, ['teacher', 'student'], true)
            ? $session['role']
            : 'student';

        $user = $registration->register($ctx->telegramUserId, $name, $role);

        SessionService::clearSession($ctx->telegramUserId, 'registration');

        app(AuditService::class)->logRegistration($user, $user->role);

        if ($user->role === 'admin') {
            $menu = Menus::adminMain();
            $client->sendMessage($ctx->chatId, $menu['text'], $menu['buttons']);

            return;
        }

        if ($user->role === 'teacher') {
            $client->sendMessage($ctx->chatId, __('bot.waiting_approval'));
            $this->notifyAdmins($client, __('bot.new_teacher_notice', ['name' => $user->full_name]));

            return;
        }

        $client->sendMessage($ctx->chatId, __('bot.registration_complete'));

        $menu = Menus::main($user);
        $client->sendMessage($ctx->chatId, $menu['text'], $menu['buttons']);

        // Taklif havolasi orqali kelgan bo'lsa, ulanish so'rovini yuboramiz.
        if (! empty($session['join_token'])) {
            $result = app(ConnectionService::class)->requestJoin($user, (string) $session['join_token']);

            $client->sendMessage(
                $ctx->chatId,
                __(ConnectionService::joinMessageKey($result['status']))
            );
        }
    }

    protected function notifyAdmins(TelegramClient $client, string $message): void
    {
        $keyboard = [[
            Keyboard::button(__('bot.btn_approve_teachers'), 'admin:approve_teachers'),
        ]];

        foreach (app(RegistrationService::class)->adminTelegramIds() as $adminId) {
            $client->sendMessage($adminId, $message, $keyboard);
        }
    }
}
