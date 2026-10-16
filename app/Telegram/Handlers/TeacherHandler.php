<?php

namespace App\Telegram\Handlers;

use App\Http\Controllers\TeacherController;
use App\Models\Material;
use App\Models\TeacherStudent;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ConnectionService;
use App\Services\InviteService;
use App\Services\SessionService;
use App\Telegram\Keyboard;
use App\Telegram\TelegramClient;
use App\Telegram\UpdateContext;
use Illuminate\Support\Str;

class TeacherHandler
{
    public function handle(
        UpdateContext $ctx,
        TelegramClient $client,
        array $parts
    ): void {
        $action = $parts[1] ?? null;
        $telegramUserId = $ctx->telegramUserId;

        if (in_array($action, ['cat', 'vis'], true)) {
            $user = User::where(
                'telegram_user_id',
                $telegramUserId
            )->first();

            if (
                ! $user ||
                $user->role !== 'teacher' ||
                $user->status !== 'active'
            ) {
                $client->answerCallbackQuery(
                    $this->getCallbackId($ctx),
                    '❌ Faol ustoz akkaunti talab qilinadi.',
                    true
                );

                return;
            }

            $controller = app(TeacherController::class);

            if ($action === 'cat') {
                $controller->selectUploadCategory(
                    $telegramUserId,
                    (string) ($parts[2] ?? '')
                );
            } else {
                $controller->selectUploadVisibility(
                    $telegramUserId,
                    (string) ($parts[2] ?? '')
                );
            }

            $client->answerCallbackQuery(
                $this->getCallbackId($ctx)
            );

            return;
        }

        // Upload callbacklari uchun foydalanuvchini tekshiramiz.
        if (in_array($action, ['save', 'cancel', 'edit', 'back'], true)) {
            $user = User::where('telegram_user_id', $telegramUserId)->first();

            if (! $user || $user->role !== 'teacher' || $user->status !== 'active') {
                $client->answerCallbackQuery(
                    $this->getCallbackId($ctx),
                    '❌ Ustoz ruxsati talab qilinadi.',
                    true
                );

                return;
            }

            $controller = app(TeacherController::class);

            switch ($action) {
                case 'save':
                    $controller->confirmUpload($telegramUserId);
                    break;

                case 'cancel':
                    $controller->cancelWizard($telegramUserId);
                    break;

                case 'edit':
                case 'back':
                    $controller->editUpload($telegramUserId);
                    break;
            }

            $client->answerCallbackQuery($this->getCallbackId($ctx));

            return;
        }

        match ($action) {
            'upload' => $this->upload($ctx, $client),
            'files', 'materials' => $this->listFiles($ctx, $client),
            'students' => $this->listStudents($ctx, $client),
            'students_pending' => $this->listPendingStudents($ctx, $client),
            'approve_student' => $this->approveStudent($ctx, $client, (int) ($parts[2] ?? 0)),
            'reject_student' => $this->rejectStudent($ctx, $client, (int) ($parts[2] ?? 0)),
            'remove_student' => $this->removeStudent($ctx, $client, (int) ($parts[2] ?? 0)),
            'link' => $this->showInviteLink($ctx, $client),
            'stats' => $this->showStats($ctx, $client),
            'search' => $this->showSearchOptions($ctx, $client),
            'search_my' => $this->startSearch($ctx, $client, 'my'),
            'search_shared' => $this->startSearch($ctx, $client, 'shared'),
            'settings' => $this->showSettings($ctx, $client),
            'view' => $this->viewMaterial($ctx, $client, (int) ($parts[2] ?? 0)),
            default => $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                __('bot.unknown_action')
            ),
        };
    }

    protected function upload(UpdateContext $ctx, TelegramClient $client): void
    {
        $user = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        if (! $user || $user->role !== 'teacher' || $user->status !== 'active') {
            $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                '❌ Tasdiqlangan ustoz bo\'lishingiz kerak.',
                true
            );

            return;
        }

        SessionService::startWizard(
            $ctx->telegramUserId,
            'upload',
            'awaiting_file'
        );

        // Log upload start
        app(AuditService::class)->log('upload_started', 'material', null, $user);

        $client->sendMessage(
            $ctx->chatId,
            '📤 Iltimos, yuklamoqchi bo\'lgan PDF yoki boshqa ruxsat etilgan faylingizni yuboring.'
        );

        $client->answerCallbackQuery($this->getCallbackId($ctx));
    }

    protected function listFiles(UpdateContext $ctx, TelegramClient $client): void
    {
        $user = User::where(
            'telegram_user_id',
            $ctx->telegramUserId
        )->first();

        if (! $user || $user->role !== 'teacher' || $user->status !== 'active') {
            $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                __('bot.unauthorized'),
                true
            );

            return;
        }

        $materials = Material::where('teacher_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->limit(20)
            ->get();

        $text = "📚 <b>Mening fayllarim</b>\n\n";
        $buttons = [];

        if ($materials->isEmpty()) {
            $text .= 'Hozircha sizda faol materiallar yo‘q.';
        } else {
            $text .= "Materialni tanlang:\n";

            foreach ($materials as $material) {
                $buttons[] = [
                    Keyboard::button(
                        '📄 '.Str::limit(
                            $material->title,
                            35
                        ),
                        'teacher:view:'.$material->id
                    ),
                ];
            }
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

        $client->answerCallbackQuery($this->getCallbackId($ctx));
    }

    protected function listStudents(UpdateContext $ctx, TelegramClient $client): void
    {
        $user = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        if (! $user || $user->role !== 'teacher') {
            $client->sendMessage($ctx->chatId, __('bot.unauthorized'));

            return;
        }

        $inviteService = app(InviteService::class);
        $students = $inviteService->getActiveStudents($user, 20);

        if ($students->isEmpty()) {
            $client->sendMessage($ctx->chatId, __('bot.teacher_no_students'));

            return;
        }

        $text = "✅ <b>Faol O'quvchilar</b> ({$students->count()} ta)\n\n";

        foreach ($students as $conn) {
            $student = $conn->student;
            $text .= "• {$student->full_name}\n";
        }

        $buttons = [
            [Keyboard::button(__('bot.btn_back'), 'menu:main')],
        ];

        $client->sendMessage($ctx->chatId, $text, $buttons);
    }

    protected function listPendingStudents(UpdateContext $ctx, TelegramClient $client): void
    {
        $user = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        if (! $user || $user->role !== 'teacher') {
            $client->sendMessage($ctx->chatId, __('bot.unauthorized'));

            return;
        }

        $inviteService = app(InviteService::class);
        $pending = $inviteService->getStudentRequests($user, 20);

        if ($pending->isEmpty()) {
            $client->sendMessage($ctx->chatId, __('bot.no_pending_requests'));

            return;
        }

        $text = "⏳ <b>Kutilayotgan So'rovlar</b> ({$pending->count()} ta)\n\n";

        $keyboard = [];
        foreach ($pending as $conn) {
            $student = $conn->student;
            $text .= "• {$student->full_name}\n";
            $keyboard[] = [
                Keyboard::button('✅ '.$student->full_name, 'teacher:approve_student:'.$conn->id),
            ];
        }

        $keyboard[] = [Keyboard::button(__('bot.btn_back'), 'menu:main')];

        $client->sendMessage($ctx->chatId, $text, $keyboard);
    }

    protected function approveStudent(UpdateContext $ctx, TelegramClient $client, int $connectionId): void
    {
        $user = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        if (! $user || $user->role !== 'teacher') {
            $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.unauthorized'), true);

            return;
        }

        $connection = TeacherStudent::find($connectionId);

        if (! $connection || $connection->teacher_id !== $user->id) {
            $client->answerCallbackQuery($this->getCallbackId($ctx), 'Bog\'lanish topilmadi.', true);

            return;
        }

        $connectionService = app(ConnectionService::class);
        $connectionService->approveStudentRequest($user, $connection->student);

        $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.student_approved'));

        $client->sendMessage(
            $ctx->chatId,
            "✅ <b>{$connection->student->full_name}</b> tasdiqlandi."
        );
    }

    protected function rejectStudent(UpdateContext $ctx, TelegramClient $client, int $connectionId): void
    {
        $user = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        if (! $user || $user->role !== 'teacher') {
            $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.unauthorized'), true);

            return;
        }

        $connection = TeacherStudent::find($connectionId);

        if (! $connection || $connection->teacher_id !== $user->id) {
            $client->answerCallbackQuery($this->getCallbackId($ctx), 'Bog\'lanish topilmadi.', true);

            return;
        }

        $connectionService = app(ConnectionService::class);
        $connectionService->rejectStudentRequest($user, $connection->student);

        $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.student_rejected'));

        $client->sendMessage(
            $ctx->chatId,
            "❌ <b>{$connection->student->full_name}</b> rad etildi."
        );
    }

    protected function removeStudent(UpdateContext $ctx, TelegramClient $client, int $connectionId): void
    {
        $user = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        if (! $user || $user->role !== 'teacher') {
            $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.unauthorized'), true);

            return;
        }

        $connection = TeacherStudent::find($connectionId);

        if (! $connection || $connection->teacher_id !== $user->id) {
            $client->answerCallbackQuery($this->getCallbackId($ctx), 'Bog\'lanish topilmadi.', true);

            return;
        }

        $connectionService = app(ConnectionService::class);
        $connectionService->removeStudentAccess($user, $connection->student);

        $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.student_removed'));

        $client->sendMessage(
            $ctx->chatId,
            "🗑 <b>{$connection->student->full_name}</b> udalindi."
        );
    }

    protected function showInviteLink(UpdateContext $ctx, TelegramClient $client): void
    {
        $user = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        if (! $user || $user->role !== 'teacher' || $user->status !== 'active') {
            $client->sendMessage($ctx->chatId, __('bot.unauthorized'));

            return;
        }

        $connectionService = app(ConnectionService::class);
        $link = $connectionService->getInviteLink($user);

        $text = __('bot.invite_link')."\n\n";
        $text .= "<code>{$link}</code>\n\n";
        $text .= __('bot.copy_link');

        $buttons = [
            [Keyboard::button(__('bot.btn_back'), 'menu:main')],
        ];

        $client->sendMessage($ctx->chatId, $text, $buttons);
    }

    protected function showSearchOptions(
        UpdateContext $ctx,
        TelegramClient $client
    ): void {
        $user = User::where(
            'telegram_user_id',
            $ctx->telegramUserId
        )->first();

        if (! $user || $user->role !== 'teacher' || $user->status !== 'active') {
            $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                __('bot.unauthorized'),
                true
            );

            return;
        }

        $text = "🔎 <b>Material qidirish</b>\n\n";
        $text .= 'Qaysi manbadan qidirmoqchisiz?';

        $buttons = [
            [
                Keyboard::button(
                    '📚 Mening fayllarim',
                    'teacher:search_my'
                ),
            ],
            [
                Keyboard::button(
                    '🌍 Umumiy bazadan qidirish',
                    'teacher:search_shared'
                ),
            ],
            [
                Keyboard::button(
                    __('bot.btn_back'),
                    'menu:main'
                ),
            ],
        ];

        $client->editOrSendMessage(
            $ctx->chatId,
            $ctx->messageId,
            $text,
            $buttons
        );

        $client->answerCallbackQuery($this->getCallbackId($ctx));
    }

    protected function startSearch(
        UpdateContext $ctx,
        TelegramClient $client,
        string $scope
    ): void {
        $user = User::where(
            'telegram_user_id',
            $ctx->telegramUserId
        )->first();

        if (! $user || $user->role !== 'teacher' || $user->status !== 'active') {
            $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                __('bot.unauthorized'),
                true
            );

            return;
        }

        SessionService::startWizard(
            $ctx->telegramUserId,
            'search',
            'enter_query',
            [
                'scope' => $scope,
                'message_id' => $ctx->messageId,
            ]
        );

        $text = $scope === 'my'
            ? "📚 <b>Mening fayllarim</b>\n\nQidiriladigan material nomini yuboring."
            : "🌍 <b>Umumiy baza</b>\n\nQidiriladigan material nomini yuboring.";

        $buttons = [
            [
                Keyboard::button(
                    __('bot.btn_back'),
                    'teacher:search'
                ),
            ],
        ];

        $client->editOrSendMessage(
            $ctx->chatId,
            $ctx->messageId,
            $text,
            $buttons
        );

        $client->answerCallbackQuery($this->getCallbackId($ctx));
    }

    protected function getCallbackId(UpdateContext $ctx): string
    {
        return $ctx->rawUpdate['callback_query']['id'] ?? '';
    }

    protected function showStats(UpdateContext $ctx, TelegramClient $client): void
    {
        $user = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        if (! $user || $user->role !== 'teacher' || $user->status !== 'active') {
            $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                __('bot.unauthorized'),
                true
            );

            return;
        }

        $materials = Material::where('teacher_id', $user->id);

        $total = (clone $materials)->count();
        $active = (clone $materials)->where('status', 'active')->count();
        $public = (clone $materials)->where('visibility', 'public')->count();
        $private = (clone $materials)->where('visibility', 'private')->count();

        $students = TeacherStudent::where('teacher_id', $user->id)
            ->where('status', 'active')
            ->count();

        $text = "📊 <b>Statistika</b>\n\n";
        $text .= "📚 Jami materiallar: {$total}\n";
        $text .= "✅ Faol materiallar: {$active}\n";
        $text .= "🌍 Ommaviy materiallar: {$public}\n";
        $text .= "🔒 Shaxsiy materiallar: {$private}\n";
        $text .= "👨‍🎓 Tasdiqlangan o‘quvchilar: {$students}";

        $client->editOrSendMessage(
            $ctx->chatId,
            $ctx->messageId,
            $text,
            [
                [Keyboard::button(__('bot.btn_back'), 'menu:main')],
            ]
        );

        $client->answerCallbackQuery($this->getCallbackId($ctx));
    }

    protected function showSettings(UpdateContext $ctx, TelegramClient $client): void
    {
        $user = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        if (! $user || $user->role !== 'teacher' || $user->status !== 'active') {
            $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                __('bot.unauthorized'),
                true
            );

            return;
        }

        $text = "⚙️ <b>Ustoz sozlamalari</b>\n\n";
        $text .= '👤 Ism: '.e($user->full_name)."\n";
        $text .= '📊 Holat: '.e($user->status)."\n";
        $text .= '🆔 Telegram ID: '.$ctx->telegramUserId."\n\n";
        $text .= 'Hozircha bu bo‘limda o‘zgartiriladigan sozlamalar mavjud emas.';

        $client->editOrSendMessage(
            $ctx->chatId,
            $ctx->messageId,
            $text,
            [
                [Keyboard::button(__('bot.btn_back'), 'menu:main')],
            ]
        );

        $client->answerCallbackQuery($this->getCallbackId($ctx));
    }

    protected function viewMaterial(
        UpdateContext $ctx,
        TelegramClient $client,
        int $materialId
    ): void {
        $user = User::where('telegram_user_id', $ctx->telegramUserId)->first();

        if (! $user || $user->role !== 'teacher' || $user->status !== 'active') {
            $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                __('bot.unauthorized'),
                true
            );

            return;
        }

        $material = Material::where('id', $materialId)
            ->where('teacher_id', $user->id)
            ->first();

        if (! $material) {
            $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                'Fayl topilmadi.',
                true
            );

            return;
        }

        $text = '📄 <b>'.e($material->title)."</b>\n\n";
        $text .= '📁 Fayl: '.e($material->original_filename ?? '—')."\n";
        $text .= '🔒 Ko‘rinish: '.($material->isPublic() ? 'Ommaviy' : 'Shaxsiy')."\n";
        $text .= '📊 Holat: '.e($material->status)."\n";
        $text .= '📦 Hajm: '.$this->formatBytes((int) $material->size_bytes)."\n";

        if ($material->description) {
            $text .= "\n📝 Tavsif: ".e($material->description);
        }

        $client->editOrSendMessage(
            $ctx->chatId,
            $ctx->messageId,
            $text,
            [
                [Keyboard::button(__('bot.btn_back'), 'teacher:files')],
            ]
        );

        $client->answerCallbackQuery($this->getCallbackId($ctx));
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return round($size, 2).' '.$units[$index];
    }
}
