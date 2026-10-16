<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Material;
use App\Models\MaterialVersion;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\SessionService;
use App\Services\StorageService;
use App\Services\TelegramService;
use App\Services\UploadValidator;
use App\Telegram\Menus;
use App\Telegram\TelegramClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TeacherController extends Controller
{
    private const TITLE_MAX = 255;

    private const DESCRIPTION_MAX = 2000;

    public function __construct(
        protected TelegramService $telegramService,
        protected StorageService $storageService,
        protected PermissionService $permissionService,
        protected UploadValidator $uploadValidator,
        protected AuditService $auditService
    ) {}

    /**
     * Ustoz yuborgan faylni tekshiradi, vaqtincha saqlaydi va metadata bosqichini boshlaydi.
     */
    public function uploadFile(User $user, array $fileInfo): void
    {
        if (! $this->permissionService->canUpload($user)) {
            $this->telegramService->sendMessage($user->telegram_user_id, __('bot.unauthorized'));

            return;
        }

        $originalName = $fileInfo['filename'] ?? 'document';
        $validation = $this->uploadValidator->validate($fileInfo['file'], $user, $originalName);

        if (isset($validation['error'])) {
            $this->telegramService->sendMessage($user->telegram_user_id, $validation['error']);

            return;
        }

        $stored = $this->storageService->storeTemporaryFile($fileInfo['file'], $originalName);

        SessionService::startWizard(
            $user->telegram_user_id,
            'upload',
            'enter_title',
            [
                'file_path' => $stored['path'],
                'file_size' => $validation['size'],
                'mime_type' => $validation['mime'],
                'checksum' => $validation['checksum'],
                'original_name' => $stored['original_name'],
            ]
        );

        $this->askMetadataStep($user->telegram_user_id, 'enter_title');
    }

    /**
     * Matn orqali kiritiladigan bosqichlar: sarlavha, tavsif, kategoriya (raqam), ko'rinish.
     */
    public function handleUploadText(int $telegramUserId, string $input): void
    {
        $session = SessionService::getSession($telegramUserId, 'upload');

        if (! $session) {
            $this->telegramService->sendMessage(
                $telegramUserId,
                '❌ Sessiya tugagan. Fayl yuklashni qaytadan boshlang.'
            );

            return;
        }

        $input = trim($input);
        $step = $session['current_step'] ?? '';

        if ($step === 'enter_title') {
            if (mb_strlen($input) > self::TITLE_MAX) {
                $this->telegramService->sendMessage($telegramUserId, __('bot.upload_title_too_long'));

                return;
            }

            $title = $input === '-'
                ? pathinfo($session['original_name'], PATHINFO_FILENAME)
                : $input;

            SessionService::updateWizard($telegramUserId, 'upload', 'enter_description', ['title' => $title]);
            $this->askMetadataStep($telegramUserId, 'enter_description');

            return;
        }

        if ($step === 'enter_description') {
            if (mb_strlen($input) > self::DESCRIPTION_MAX) {
                $this->telegramService->sendMessage($telegramUserId, __('bot.upload_description_too_long'));

                return;
            }

            SessionService::updateWizard($telegramUserId, 'upload', 'enter_category', [
                'description' => $input === '-' ? null : $input,
            ]);
            $this->askMetadataStep($telegramUserId, 'enter_category');

            return;
        }

        if ($step === 'enter_category') {
            if ($input === '-') {
                $data = ['category_id' => null, 'category_name' => null];
            } else {
                $categories = $this->activeCategories();

                if (! ctype_digit($input) || ! $categories->has((int) $input - 1)) {
                    $this->askMetadataStep($telegramUserId, 'enter_category');

                    return;
                }

                $category = $categories->values()[(int) $input - 1];
                $data = ['category_id' => $category->id, 'category_name' => $category->name];
            }

            SessionService::updateWizard($telegramUserId, 'upload', 'enter_visibility', $data);
            $this->askMetadataStep($telegramUserId, 'enter_visibility');

            return;
        }

        if ($step === 'enter_visibility') {
            $visibility = strtolower($input);

            if (! in_array($visibility, ['public', 'private'], true)) {
                $this->telegramService->sendMessage($telegramUserId, '❌ Faqat public yoki private deb yozing.');

                return;
            }

            SessionService::updateWizard($telegramUserId, 'upload', 'confirm', ['visibility' => $visibility]);
            $this->showConfirmation($telegramUserId);

            return;
        }

        if ($step === 'confirm') {
            $this->telegramService->sendMessage(
                $telegramUserId,
                'Quyidagi tugmalardan birini tanlang: Saqlash, Tahrirlash yoki Bekor qilish.'
            );
        }
    }

    public function selectUploadCategory(int $telegramUserId, string $categoryId): void
    {
        $session = SessionService::getSession($telegramUserId, 'upload');

        if (! $session || ($session['current_step'] ?? '') !== 'enter_category') {
            $this->telegramService->sendMessage($telegramUserId, '❌ Yuklash sessiyasi tugagan. Qaytadan boshlang.');

            return;
        }

        if ($categoryId === 'none') {
            $data = ['category_id' => null, 'category_name' => null];
        } else {
            $category = Category::where('is_active', true)->where('id', (int) $categoryId)->first();

            if (! $category) {
                $this->telegramService->sendMessage($telegramUserId, '❌ Kategoriya topilmadi. Qaytadan tanlang.');
                $this->askMetadataStep($telegramUserId, 'enter_category');

                return;
            }

            $data = ['category_id' => $category->id, 'category_name' => $category->name];
        }

        SessionService::updateWizard($telegramUserId, 'upload', 'enter_visibility', $data);
        $this->askMetadataStep($telegramUserId, 'enter_visibility');
    }

    public function selectUploadVisibility(int $telegramUserId, string $visibility): void
    {
        $session = SessionService::getSession($telegramUserId, 'upload');

        if (! $session || ($session['current_step'] ?? '') !== 'enter_visibility') {
            $this->telegramService->sendMessage($telegramUserId, '❌ Yuklash sessiyasi tugagan. Qaytadan boshlang.');

            return;
        }

        if (! in_array($visibility, ['public', 'private'], true)) {
            return;
        }

        SessionService::updateWizard($telegramUserId, 'upload', 'confirm', ['visibility' => $visibility]);
        $this->showConfirmation($telegramUserId);
    }

    public function editUpload(int $telegramUserId): void
    {
        $session = SessionService::getSession($telegramUserId, 'upload');

        if (! $session) {
            $this->telegramService->sendMessage($telegramUserId, '❌ Yuklash sessiyasi tugagan.');

            return;
        }

        SessionService::updateWizard($telegramUserId, 'upload', 'enter_title', []);
        $this->askMetadataStep($telegramUserId, 'enter_title');
    }

    /**
     * Faylni doimiy joyga ko'chiradi va bazaga bitta tranzaksiyada yozadi.
     */
    public function confirmUpload(int $telegramUserId): void
    {
        $user = User::where('telegram_user_id', $telegramUserId)->first();

        if (! $user || ! $this->permissionService->canUpload($user)) {
            $this->telegramService->sendMessage($telegramUserId, __('bot.unauthorized'));

            return;
        }

        $session = SessionService::getSession($telegramUserId, 'upload');

        if (! $session) {
            $this->telegramService->sendMessage($telegramUserId, '❌ Sessiya tugadi.');

            return;
        }

        if (($session['current_step'] ?? '') !== 'confirm') {
            $this->telegramService->sendMessage($telegramUserId, '❌ Avval metadata bosqichlarini yakunlang.');

            return;
        }

        try {
            $permanentPath = $this->storageService->promoteToPermanent($session['file_path']);
        } catch (Throwable $e) {
            Log::error('Upload: could not promote file', [
                'telegram_user_id' => $telegramUserId,
                'error' => $e->getMessage(),
            ]);
            $this->telegramService->sendMessage($telegramUserId, __('bot.upload_save_failed'));

            return;
        }

        try {
            $material = DB::transaction(function () use ($user, $session, $permanentPath) {
                $material = Material::create([
                    'teacher_id' => $user->id,
                    'original_filename' => $session['original_name'],
                    'title' => $session['title'] ?? pathinfo($session['original_name'], PATHINFO_FILENAME),
                    'description' => $session['description'] ?? null,
                    'category_id' => $session['category_id'] ?? null,
                    'visibility' => $session['visibility'] ?? 'public',
                    'status' => 'active',
                    'storage_disk' => 'private_materials',
                    'storage_path' => $permanentPath,
                    'size_bytes' => $session['file_size'],
                    'mime_type' => $session['mime_type'],
                    'checksum' => $session['checksum'],
                ]);

                $version = MaterialVersion::create([
                    'material_id' => $material->id,
                    'version_number' => 1,
                    'storage_path' => $permanentPath,
                    'size_bytes' => $session['file_size'],
                    'checksum' => $session['checksum'],
                    'uploaded_at' => now(),
                    'status' => 'active',
                ]);

                $material->update(['current_version_id' => $version->id]);

                return $material;
            });
        } catch (Throwable $e) {
            // Bazaga yozilmadi: nusxa qilingan doimiy faylni olib tashlaymiz, vaqtinchalik fayl
            // va sessiya saqlanadi, ustoz qayta urinib ko'ra oladi.
            $this->storageService->deleteFile($permanentPath);

            Log::error('Upload: could not save material', [
                'telegram_user_id' => $telegramUserId,
                'error' => $e->getMessage(),
            ]);
            $this->telegramService->sendMessage($telegramUserId, __('bot.upload_save_failed'));

            return;
        }

        $this->storageService->deleteFile($session['file_path']);
        $this->auditService->logFileUploaded($user, $material->id, $material->original_filename);

        SessionService::clearSession($telegramUserId, 'upload');

        $this->telegramService->sendMessage($telegramUserId, sprintf(__('bot.file_saved'), $material->id));
        $this->showTeacherMenu($telegramUserId);
    }

    public function cancelWizard(int $telegramUserId): void
    {
        $session = SessionService::getSession($telegramUserId, 'upload');

        if ($session && isset($session['file_path'])) {
            $this->storageService->deleteFile($session['file_path']);
        }

        SessionService::clearSession($telegramUserId, 'upload');

        $this->telegramService->sendMessage($telegramUserId, __('bot.upload_cancelled'));
        $this->showTeacherMenu($telegramUserId);
    }

    private function showTeacherMenu(int $telegramUserId): void
    {
        $menu = Menus::teacherMain();

        $this->telegramService->sendMessage($telegramUserId, $menu['text'], $menu['buttons']);
    }

    private function activeCategories()
    {
        return Category::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function showConfirmation(int $telegramUserId): void
    {
        $session = SessionService::getSession($telegramUserId, 'upload') ?? [];

        $text = "✅ Tasdiqlang:\n\n";
        $text .= '📄 Fayl: '.e($session['original_name'] ?? '')."\n";
        $text .= '📝 Sarlavha: '.e($session['title'] ?? '(standart nom)')."\n";
        $text .= '💬 Tavsif: '.e($session['description'] ?? '(bo‘sh)')."\n";
        $text .= '🏷 Kategoriya: '.e($session['category_name'] ?? 'Tanlanmagan')."\n";
        $text .= '🔒 Ko‘rinish: '.e($session['visibility'] ?? 'public')."\n\n";

        if (($session['visibility'] ?? 'public') === 'public') {
            $text .= "⚠️ PUBLIC — barcha ro‘yxatdan o‘tgan o‘quvchilar topa oladi!\n";
        }

        $this->sendUploadStepMessage($telegramUserId, $text, [
            ['text' => '✅ Saqlash', 'callback_data' => 'upload:save'],
            ['text' => '⬅️ Tahrirlash', 'callback_data' => 'upload:edit'],
            ['text' => '✖️ Bekor', 'callback_data' => 'upload:cancel'],
        ]);
    }

    private function askMetadataStep(int $telegramUserId, string $step): void
    {
        if ($step === 'enter_title') {
            $this->sendUploadStepMessage(
                $telegramUserId,
                "📝 Fayl sarlavhasini yuboring.\nFayl nomidan foydalanish uchun - yuboring."
            );

            return;
        }

        if ($step === 'enter_description') {
            $this->sendUploadStepMessage(
                $telegramUserId,
                "💬 Fayl tavsifini yuboring.\nTavsif kiritmaslik uchun - yuboring."
            );

            return;
        }

        if ($step === 'enter_category') {
            $categories = $this->activeCategories();

            $text = "🏷 <b>Kategoriyani tanlang</b>\n\n";
            $buttons = [];

            foreach ($categories as $category) {
                $buttons[] = [
                    'text' => $category->name,
                    'callback_data' => 'upload:cat:'.$category->id,
                ];
            }

            if ($categories->isEmpty()) {
                $text .= "Hozircha faol kategoriyalar mavjud emas.\n";
            }

            $buttons[] = ['text' => '⏭ Kategoriyasiz davom etish', 'callback_data' => 'upload:cat:none'];
            $buttons[] = ['text' => '❌ Bekor qilish', 'callback_data' => 'upload:cancel'];

            $this->sendUploadStepMessage($telegramUserId, $text, $buttons);

            return;
        }

        if ($step === 'enter_visibility') {
            $text = "🔒 <b>Fayl ko‘rinishini tanlang</b>\n\n";
            $text .= "🌐 Hammaga ochiq — o‘quvchilar materialni topishi mumkin.\n";
            $text .= '🔒 Shaxsiy — faqat ruxsat berilgan foydalanuvchilar.';

            $this->sendUploadStepMessage($telegramUserId, $text, [
                ['text' => '🌐 Hammaga ochiq', 'callback_data' => 'upload:vis:public'],
                ['text' => '🔒 Shaxsiy', 'callback_data' => 'upload:vis:private'],
                ['text' => '❌ Bekor qilish', 'callback_data' => 'upload:cancel'],
            ]);
        }
    }

    /**
     * Bosqich xabarini yuboradi yoki oldingisini tahrirlaydi (chat to'lib ketmasligi uchun).
     */
    private function sendUploadStepMessage(int $telegramUserId, string $text, array $buttons = []): void
    {
        $session = SessionService::getSession($telegramUserId, 'upload') ?? [];

        $messageId = isset($session['message_id']) ? (int) $session['message_id'] : null;

        $keyboard = $buttons !== [] ? $this->telegramService->buildInlineKeyboard($buttons) : null;

        $result = app(TelegramClient::class)->editOrSendMessage($telegramUserId, $messageId, $text, $keyboard);

        if (empty($result['ok'])) {
            return;
        }

        $newMessageId = $result['result']['message_id'] ?? $messageId;

        if ($newMessageId === null) {
            return;
        }

        SessionService::updateWizard(
            $telegramUserId,
            'upload',
            $session['current_step'] ?? 'enter_title',
            ['message_id' => (int) $newMessageId]
        );
    }
}
