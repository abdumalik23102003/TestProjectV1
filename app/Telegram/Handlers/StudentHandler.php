<?php

namespace App\Telegram\Handlers;

use App\Http\Controllers\StudentController;
use App\Models\User;
use App\Telegram\UpdateContext;
use App\Telegram\TelegramClient;
use App\Telegram\Keyboard;
use App\Services\SessionService;
use App\Services\AuditService;
use App\Services\InviteService;

class StudentHandler
{
    public function handle(UpdateContext $ctx, TelegramClient $client, array $parts): void
    {
        $action = $parts[1] ?? null;

        match ($action) {
            'search' => $this->search($ctx, $client),
            'teachers' => $this->listTeachers($ctx, $client),
            'recent' => $this->listRecent($ctx, $client),
            'profile' => $this->showProfile($ctx, $client),
            'search_next' => $this->changeSearchPage(
                $ctx,
                $client,
                (int) ($parts[2] ?? 1)
            ),
            'search_prev' => $this->changeSearchPage(
                $ctx,
                $client,
                (int) ($parts[2] ?? 1)
            ),
            'download' => $this->downloadMaterial(
                $ctx,
                (int) ($parts[2] ?? 0)
            ),
            default => $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.unknown_action')),
        };
    }

    protected function search(UpdateContext $ctx, TelegramClient $client): void
    {
        if ($ctx->user) {
            app(AuditService::class)->log('search_started', 'search', null, $ctx->user);
        }

        SessionService::startWizard(
            $ctx->telegramUserId,
            'search',
            'enter_query'
        );

        $client->sendMessage(
            $ctx->chatId,
            __('bot.search_prompt')
        );
    }

    protected function downloadMaterial(
        UpdateContext $ctx,
        int $materialId
    ): void {
        $callbackId = $this->getCallbackId($ctx);

        $user = User::where(
            'telegram_user_id',
            $ctx->telegramUserId
        )->first();

        if (
            !$user ||
            $user->status !== 'active' ||
            !in_array($user->role, ['teacher', 'student'], true)
        ) {
            app(TelegramClient::class)->answerCallbackQuery(
                $callbackId,
                __('bot.unauthorized'),
                true
            );

            return;
        }

        if ($materialId < 1) {
            app(TelegramClient::class)->answerCallbackQuery(
                $callbackId,
                __('bot.unknown_action'),
                true
            );

            return;
        }

        app(TelegramClient::class)->answerCallbackQuery($callbackId);

        app(StudentController::class)->download(
            $user,
            $materialId
        );
    }

    protected function listTeachers(UpdateContext $ctx, TelegramClient $client): void
    {
        if (!$ctx->user) {
            $client->sendMessage($ctx->chatId, __('bot.unauthorized'));
            return;
        }

        $inviteService = app(InviteService::class);
        $connections = $inviteService->getTeacherConnections($ctx->user, 20);

        if ($connections->isEmpty()) {
            $text = __('bot.no_teachers') . "\n\n";
            $text .= "💡 Ustozga bog'lanish uchun ularning undirish havolasini so'ring.";
            $client->sendMessage($ctx->chatId, $text);
            return;
        }

        $text = "👨‍🏫 <b>Sizning Ustozlaringiz</b> ({$connections->count()} ta)\n\n";

        foreach ($connections as $conn) {
            $teacher = $conn->teacher;
            $text .= "• <b>{$teacher->full_name}</b>\n";
        }

        $buttons = [
            [Keyboard::button(__('bot.btn_back'), 'menu:main')],
        ];

        $client->editOrSendMessage(
            $ctx->chatId,
            $ctx->messageId,
            $text,
            $buttons
        );
    }

    protected function listRecent(UpdateContext $ctx, TelegramClient $client): void
    {
        if (!$ctx->user) {
            $client->sendMessage($ctx->chatId, __('bot.unauthorized'));
            return;
        }

        $inviteService = app(InviteService::class);
        $materials = $inviteService->getRecentMaterialsFromTeachers($ctx->user, 15);

        if ($materials->isEmpty()) {
            $client->sendMessage($ctx->chatId, __('bot.no_results'));
            return;
        }

        $text = "📥 <b>Oxirgi Materiallar</b> ({$materials->count()} ta)\n\n";

        foreach ($materials as $material) {
            $teacher = $material->teacher;
            $text .= "📄 {$material->title}\n";
            $text .= "   👨‍🏫 {$teacher->full_name}\n\n";
        }

        $buttons = [
            [Keyboard::button(__('bot.btn_back'), 'menu:main')],
        ];

        $client->sendMessage($ctx->chatId, $text, $buttons);
    }

    protected function showProfile(UpdateContext $ctx, TelegramClient $client): void
    {
        if ($ctx->user) {
            $status = __("bot.status_{$ctx->user->status}");
            $inviteService = app(InviteService::class);
            $teachersCount = $inviteService->getTeacherConnectionsCount($ctx->user);

            $text = "👤 <b>{$ctx->user->full_name}</b>\n\n";
            $text .= "🎓 <b>Rol:</b> O'quvchi\n";
            $text .= "📊 <b>Status:</b> {$status}\n";
            $text .= "👨‍🏫 <b>Ustozlar:</b> {$teachersCount} ta\n";

            $buttons = [
                [Keyboard::button(__('bot.btn_back'), 'menu:main')],
            ];

            $client->sendMessage($ctx->chatId, $text, $buttons);
        }
    }
    protected function changeSearchPage(
        UpdateContext $ctx,
        TelegramClient $client,
        int $page
    ): void {
        $user = User::where(
            'telegram_user_id',
            $ctx->telegramUserId
        )->first();

        if (
            !$user ||
            $user->status !== 'active' ||
            !in_array($user->role, ['teacher', 'student'], true)
        ) {
            $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                __('bot.unauthorized'),
                true
            );

            return;
        }

        $session = SessionService::getSession(
            $ctx->telegramUserId,
            'search'
        );

        if (
            !$session ||
            ($session['current_step'] ?? null) !== 'show_results' ||
            empty($session['query'])
        ) {
            $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                'Qidiruv sessiyasi tugagan. Qayta qidiring.',
                true
            );

            return;
        }

        $page = max(1, $page);

        $results = app(\App\Services\SearchService::class)->search(
            $user,
            $session['query'],
            $page
        );

        $client->answerCallbackQuery($this->getCallbackId($ctx));

        if ($results->isEmpty() && $page > 1) {
            $page--;

            $results = app(\App\Services\SearchService::class)->search(
                $user,
                $session['query'],
                $page
            );
        }

        $text = "🔍 <b>Qidiruv natijalari</b>\n\n";
        $buttons = [];

        foreach ($results as $material) {
            $text .= '📄 ' . e($material->title) . "\n";
            $text .= '👨‍🏫 ' . e($material->teacher?->full_name ?? 'Noma’lum ustoz') . "\n\n";

            $buttons[] = [
                Keyboard::button(
                    '⬇️ ' . \Illuminate\Support\Str::limit($material->title, 25),
                    'student:download:' . $material->id
                ),
            ];
        }

        if ($results->isEmpty()) {
            $text .= '❌ Natija topilmadi.';
        }

        if ($results->currentPage() > 1) {
            $buttons[] = [
                Keyboard::button(
                    '⬅️ Oldingi',
                    'student:search_prev:' . ($results->currentPage() - 1)
                ),
            ];
        }

        if ($results->hasMorePages()) {
            $buttons[] = [
                Keyboard::button(
                    'Keyingi ➡️',
                    'student:search_next:' . ($results->currentPage() + 1)
                ),
            ];
        }

        $buttons[] = [
            Keyboard::button(__('bot.btn_back'), 'menu:main'),
        ];

        $client->editOrSendMessage(
            $ctx->chatId,
            $ctx->messageId,
            $text,
            $buttons
        );
    }
    protected function getCallbackId(UpdateContext $ctx): string
    {
        return $ctx->rawUpdate['callback_query']['id'] ?? '';
    }
}
