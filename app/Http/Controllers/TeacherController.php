<?php

namespace App\Http\Controllers;

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

    public function __construct(
        TelegramService $telegramService,
        StorageService $storageService,
        PermissionService $permissionService
    ) {
        $this->telegramService = $telegramService;
        $this->storageService = $storageService;
        $this->permissionService = $permissionService;
    }

    public function uploadFile(User $user, array $fileInfo): void
    {
        if (!$this->permissionService->canUpload($user)) {
            $this->telegramService->sendMessage($user->telegram_user_id, "❌ Ruxsat yo'q.");
            return;
        }

        // Telegram'dan fayl yuklash va temporary saqlash
        $stored = $this->storageService->storeTemporaryFile(
            $fileInfo['file'],
            $fileInfo['filename'] ?? 'document'
        );

        // Metadata wizard boshlash
        SessionService::startWizard($user->telegram_user_id, 'upload', 'enter_title', [
            'file_path' => $stored['path'],
            'file_size' => $stored['size'],
            'mime_type' => $stored['mime'],
            'checksum' => $stored['checksum'],
            'original_name' => $stored['original_name']
        ]);

        $this->showMetadataForm($user->telegram_user_id);
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

    public function handleMetadataStep(int $telegramUserId, string $step, string $input): void
    {
        $session = SessionService::getSession($telegramUserId, 'upload');

        if (!$session) {
            $this->telegramService->sendMessage($telegramUserId, "❌ Sessiya tugadi. Qayta boshlang.");
            return;
        }

        match ($step) {
            'title' => $session['title'] = trim($input),
            'description' => $session['description'] = trim($input),
            'category_id' => $session['category_id'] = (int)$input,
            'visibility' => $session['visibility'] = strtolower($input) === 'private' ? 'private' : 'public',
        };

        SessionService::updateSessionPayload($telegramUserId, 'upload', $session);
        $this->showConfirmation($telegramUserId, $session);
    }

    private function showConfirmation(int $telegramUserId, array $session): void
    {
        $text = "✅ Tasdiqlang:\n\n";
        $text .= "📄 Fayl: " . $session['original_name'] . "\n";
        $text .= "📝 Sarlavha: " . ($session['title'] ?? '(standart nom)') . "\n";
        $text .= "💬 Tavsif: " . ($session['description'] ?? '(bo\'sh)') . "\n";
        $text .= "🏷 Kategoriya: " . ($session['category_id'] ?? 'Tanlanmagan') . "\n";
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
            'storage_disk' => config('filesystems.disks.private_materials.driver', 'private_materials'),
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
