<?php

namespace App\Telegram\Handlers;

use App\Http\Controllers\StudentController;
use App\Services\SearchService;
use App\Services\SessionService;
use App\Telegram\Keyboard;
use App\Telegram\TelegramClient;
use App\Telegram\UpdateContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Qidiruv wizardi: foydalanuvchi qidiruv so'zini yuborganda ishlaydi.
 * Matn shu handler tomonidan qabul qilinsa true qaytaradi.
 */
class SearchInputHandler
{
    public function handle(UpdateContext $ctx, TelegramClient $client): bool
    {
        if ($ctx->messageText === null) {
            return false;
        }

        $session = SessionService::getSession($ctx->telegramUserId, 'search');

        if (! $session || ($session['current_step'] ?? null) !== 'enter_query') {
            return false;
        }

        $query = trim($ctx->messageText);
        $scope = $session['scope'] ?? null;
        $user = $ctx->user;
        $messageId = isset($session['message_id']) ? (int) $session['message_id'] : null;

        $scopeAllowed = $scope === null
            || ($user && $user->role === 'teacher' && in_array($scope, ['my', 'shared'], true));

        if (! $user || $user->status !== 'active' || ! $scopeAllowed) {
            SessionService::clearSession($ctx->telegramUserId, 'search');
            $client->sendMessage($ctx->chatId, __('bot.unknown_action'));

            return true;
        }

        $backTo = $scope === null ? 'menu:main' : 'teacher:search';

        if (mb_strlen($query) < 2) {
            $client->editOrSendMessage(
                $ctx->chatId,
                $messageId,
                __('bot.search_too_short'),
                [[Keyboard::button(__('bot.btn_back'), $backTo)]]
            );

            return true;
        }

        try {
            // O'quvchi qidiruvi: natijalar sahifalanadi, shuning uchun sessiya saqlanadi.
            if ($scope === null) {
                SessionService::updateWizard($ctx->telegramUserId, 'search', 'show_results', ['query' => $query]);
                app(StudentController::class)->search($user, $query);

                return true;
            }

            $materials = app(SearchService::class)->searchForTeacher($user, $query, $scope, 10);

            $text = __($scope === 'my' ? 'bot.search_title_my' : 'bot.search_title_shared');
            $buttons = [];

            if ($materials->isEmpty()) {
                $text .= __('bot.search_empty');
            }

            foreach ($materials as $material) {
                $text .= '📄 <b>'.e($material->title)."</b>\n";
                $text .= '👨‍🏫 '.e($material->teacher?->full_name ?? __('bot.unknown_teacher'))."\n";

                if ($material->description) {
                    $text .= e(Str::limit($material->description, 100))."\n";
                }

                $text .= "\n";

                $buttons[] = [
                    Keyboard::button(
                        '📄 '.Str::limit($material->title, 30),
                        ($scope === 'my' ? 'teacher:view:' : 'student:download:').$material->id
                    ),
                ];
            }

            $buttons[] = [Keyboard::button(__('bot.search_another'), 'teacher:search')];
            $buttons[] = [Keyboard::button(__('bot.btn_back'), 'menu:main')];

            $client->editOrSendMessage($ctx->chatId, $messageId, $text, $buttons);
        } catch (Throwable $e) {
            Log::error('Material search failed', [
                'telegram_user_id' => $ctx->telegramUserId,
                'scope' => $scope,
                'error' => $e->getMessage(),
            ]);

            $client->editOrSendMessage(
                $ctx->chatId,
                $messageId,
                __('bot.search_failed'),
                [[Keyboard::button(__('bot.btn_back'), $backTo)]]
            );
        }

        SessionService::clearSession($ctx->telegramUserId, 'search');

        return true;
    }
}
