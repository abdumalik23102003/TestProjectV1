<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\UploadValidator;
use App\Models\User;
use App\Models\Material;
use App\Models\MaterialVersion;
use App\Models\AuditLog;
use App\Services\TelegramService;
use App\Services\SessionService;
use App\Services\StorageService;
use App\Services\PermissionService;
use Illuminate\Support\Str;

class TeacherController extends Controller
{
    protected TelegramService $telegramService;
    protected StorageService $storageService;
    protected PermissionService $permissionService;
    protected UploadValidator $uploadValidator;

    public function __construct(
        TelegramService   $telegramService,
        StorageService    $storageService,
        PermissionService $permissionService,
        UploadValidator   $uploadValidator
    )
    {
        $this->telegramService = $telegramService;
        $this->storageService = $storageService;
        $this->permissionService = $permissionService;
        $this->uploadValidator = $uploadValidator;
    }

    public function uploadFile(User $user, array $fileInfo): void
    {
        if (!$this->permissionService->canUpload($user)) {
            $this->telegramService->sendMessage(
                $user->telegram_user_id,
                "❌ Yuklash uchun ruxsatingiz yo‘q."
            );
            return;
        }

        $originalName = $fileInfo['filename'] ?? 'document';
        $validation = $this->uploadValidator->validate(
            $fileInfo['file'],
            $user,
            $originalName
        );

        if (isset($validation['error'])) {
            $this->telegramService->sendMessage(
                $user->telegram_user_id,
                "❌ Fayl qabul qilinmadi: {$validation['error']}"
            );
            return;
        }

        $stored = $this->storageService->storeTemporaryFile(
            $fileInfo['file'],
            $originalName
        );

        // Validator hisoblagan qiymatlarni ishlatamiz.
        $stored['size'] = $validation['size'];
        $stored['mime'] = $validation['mime'];
        $stored['checksum'] = $validation['checksum'];

        SessionService::startWizard(
            $user->telegram_user_id,
            'upload',
            'enter_title',
            [
                'file_path' => $stored['path'],
                'file_size' => $stored['size'],
                'mime_type' => $stored['mime'],
                'checksum' => $stored['checksum'],
                'original_name' => $stored['original_name'],
            ]
        );

        $this->askMetadataStep(
            $user->telegram_user_id,
            'enter_title'
        );
    }

    private function showMetadataForm(int $telegramUserId): void
    {
        $text = "📋 Metadata kiriting (ixtiyoriy):\n\n";
        $text .= "1️⃣ Sarlavha\n";
        $text .= "2️⃣ Tavsif\n";
        $text .= "3️⃣ Kategoriya\n";
        $text .= "4️⃣ Ko'rinish (public/private)\n\n";
        $text .= "Har birini alohida xabar sifatida yuboring:";

        $buttons = [
            ['text' => '⬅️ Orqaga', 'callback_data' => 'upload:back'],
            ['text' => '✖️ Bekor', 'callback_data' => 'upload:cancel']
        ];

        $this->telegramService->sendMessage($telegramUserId, $text, $buttons);
    }

    public function handleUploadText(
        int    $telegramUserId,
        string $input
    ): void
    {
        $session = SessionService::getSession($telegramUserId, 'upload');

        if (!$session) {
            $this->telegramService->sendMessage(
                $telegramUserId,
                '❌ Sessiya tugagan. Fayl yuklashni qaytadan boshlang.'
            );
            return;
        }

        $step = $session['current_step'] ?? '';

        if ($step === 'enter_title') {
            $session['title'] = trim($input) === '-'
                ? pathinfo(
                    $session['original_name'],
                    PATHINFO_FILENAME
                )
                : trim($input);

            SessionService::updateWizard(
                $telegramUserId,
                'upload',
                'enter_description',
                $session
            );

            $this->askMetadataStep($telegramUserId, 'enter_description');
            return;
        }

        if ($step === 'enter_description') {
            $session['description'] = trim($input) === '-'
                ? null
                : trim($input);

            SessionService::updateWizard(
                $telegramUserId,
                'upload',
                'enter_category',
                $session
            );

            $this->askMetadataStep($telegramUserId, 'enter_category');
            return;
        }

        if ($step === 'enter_category') {
            $input = trim($input);

            if ($input === '-') {
                $session['category_id'] = null;
            } else {
                $categories = Category::where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get();

                if (
                    !ctype_digit($input) ||
                    !$categories->has((int)$input - 1)
                ) {
                    $this->askMetadataStep(
                        $telegramUserId,
                        'enter_category'
                    );
                    return;
                }

                $category = $categories->values()[(int)$input - 1];
                $session['category_id'] = $category->id;
                $session['category_name'] = $category->name;
            }

            SessionService::updateWizard(
                $telegramUserId,
                'upload',
                'enter_visibility',
                $session
            );

            $this->askMetadataStep($telegramUserId, 'enter_visibility');
            return;
        }

        if ($step === 'enter_visibility') {
            $visibility = strtolower(trim($input));

            if (!in_array($visibility, ['public', 'private'], true)) {
                $this->telegramService->sendMessage(
                    $telegramUserId,
                    '❌ Faqat public yoki private deb yozing.'
                );
                return;
            }

            $session['visibility'] = $visibility;

            SessionService::updateWizard(
                $telegramUserId,
                'upload',
                'confirm',
                $session
            );

            $this->showConfirmation(
                $telegramUserId,
                SessionService::getSession($telegramUserId, 'upload') ?? $session
            );
            return;
        }

        if ($step === 'confirm') {
            $this->telegramService->sendMessage(
                $telegramUserId,
                'Quyidagi tugmalardan birini tanlang: Saqlash, Tahrirlash yoki Bekor qilish.'
            );
        }
    }

    private function askMetadataStep(
        int    $telegramUserId,
        string $step
    ): void
    {
        if ($step === 'enter_title') {
            $text = "📝 Fayl sarlavhasini yuboring.\n";
            $text .= "Fayl nomidan foydalanish uchun - yuboring.";
        } elseif ($step === 'enter_description') {
            $text = "💬 Fayl tavsifini yuboring.\n";
            $text .= "Tavsif kiritmaslik uchun - yuboring.";
        } elseif ($step === 'enter_category') {
            $categories = Category::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->values();

            $text = "🏷 Kategoriya raqamini yuboring:\n\n";

            foreach ($categories as $index => $category) {
                $text .= ($index + 1) . '. ' . $category->name . "\n";
            }

            $text .= "\nKategoriyasiz saqlash uchun - yuboring.";

            if ($categories->isEmpty()) {
                $text = "Faol kategoriyalar yo‘q.\nKategoriyasiz davom etish uchun - yuboring.";
            }
        } elseif ($step === 'enter_visibility') {
            $text = "🔒 Fayl ko‘rinishini tanlang.\n";
            $text .= "public — barcha tegishli o‘quvchilar topishi mumkin.\n";
            $text .= "private — faqat ruxsat berilgan foydalanuvchilar.";
        } else {
            return;
        }

        $this->telegramService->sendMessage($telegramUserId, $text);
    }

    public function editUpload(int $telegramUserId): void
    {
        $session = SessionService::getSession($telegramUserId, 'upload');

        if (!$session) {
            $this->telegramService->sendMessage(
                $telegramUserId,
                '❌ Yuklash sessiyasi tugagan.'
            );
            return;
        }

        SessionService::updateWizard(
            $telegramUserId,
            'upload',
            'enter_title',
            $session
        );

        $this->askMetadataStep($telegramUserId, 'enter_title');
    }

    private function showConfirmation(int $telegramUserId, array $session): void
    {
        $text = "✅ Tasdiqlang:\n\n";
        $text .= "📄 Fayl: " . $session['original_name'] . "\n";
        $text .= "📝 Sarlavha: " . ($session['title'] ?? '(standart nom)') . "\n";
        $text .= "💬 Tavsif: " . ($session['description'] ?? '(bo\'sh)') . "\n";
        $text .= "🏷 Kategoriya: " . ($session['category_name'] ?? 'Tanlanmagan') . "\n";
        $text .= "🔒 Ko'rinish: " . ($session['visibility'] ?? 'public') . "\n\n";

        if (($session['visibility'] ?? 'public') === 'public') {
            $text .= "⚠️ PUBLIC — barcha ro'yxatdan o'tgan o'quvchilar topa oladi!\n";
        }

        $buttons = [
            ['text' => '✅ Saqlash', 'callback_data' => 'upload:save'],
            ['text' => '⬅️ Tahrirlash', 'callback_data' => 'upload:edit'],
            ['text' => '✖️ Bekor', 'callback_data' => 'upload:cancel']
        ];

        $this->telegramService->sendMessage($telegramUserId, $text, $buttons);
    }

    public function confirmUpload(int $telegramUserId): void
    {
        $user = User::where('telegram_user_id', $telegramUserId)->firstOrFail();
        $session = SessionService::getSession($telegramUserId, 'upload');

        if (!$session) {
            $this->telegramService->sendMessage($telegramUserId, "❌ Sessiya tugadi.");
            return;
        }

        if (($session['current_step'] ?? '') !== 'confirm') {
            $this->telegramService->sendMessage(
                $telegramUserId,
                '❌ Avval metadata bosqichlarini yakunlang.'
            );
            return;
        }

        // Vaqtinchalik faylni permanent joyga ko'chirish
        $permanentPath = $this->storageService->moveToPermanent(
            $session['file_path'],
            $session['original_name']
        );

        // Material yaratish
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
            'checksum' => $session['checksum']
        ]);

        // Material versioni yaratish
        MaterialVersion::create([
            'material_id' => $material->id,
            'version_number' => 1,
            'storage_path' => $permanentPath,
            'size_bytes' => $session['file_size'],
            'checksum' => $session['checksum'],
            'uploaded_at' => now(),
            'status' => 'active'
        ]);

        // Audit log
        AuditLog::create([
            'actor_id' => $user->id,
            'action_type' => 'material_uploaded',
            'subject_type' => 'material',
            'subject_id' => $material->id,
            'metadata' => json_encode([
                'filename' => $material->original_filename,
                'size' => $material->size_bytes,
                'visibility' => $material->visibility
            ]),
            'created_at' => now()
        ]);

        SessionService::clearSession($telegramUserId, 'upload');

        $this->telegramService->sendMessage(
            $telegramUserId,
            "✅ Fayl saqlandi! ID: {$material->id}"
        );

        // Teacher menu
        app(AuthController::class)->showTeacherMenu($telegramUserId);
    }

    public function cancelWizard(int $telegramUserId): void
    {
        $session = SessionService::getSession($telegramUserId, 'upload');

        if ($session && isset($session['file_path'])) {
            $this->storageService->deleteFile($session['file_path']);
        }

        SessionService::clearSession($telegramUserId, 'upload');
        $this->telegramService->sendMessage($telegramUserId, "❌ Bekor qilindi.");
        app(AuthController::class)->showTeacherMenu($telegramUserId);
    }

    public function listMaterials(User $user): void
    {
        $materials = Material::where('teacher_id', $user->id)
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($materials->isEmpty()) {
            $this->telegramService->sendMessage($user->telegram_user_id, "📚 Fayllaringiz yo'q.");
            return;
        }

        $text = "📚 Sizning fayllaringiz:\n\n";
        $buttons = [];

        foreach ($materials as $material) {
            $text .= "📄 " . $material->title . "\n";
            $text .= "🔒 " . ($material->isPublic() ? 'Public' : 'Private') . "\n";
            $text .= "📦 " . formatBytes($material->size_bytes) . "\n\n";

            $buttons[] = [
                'text' => '📋 ' . Str::limit($material->title, 20),
                'callback_data' => "material:view:{$material->id}"
            ];
        }

        $this->telegramService->sendMessage($user->telegram_user_id, $text, $buttons);
    }
}
