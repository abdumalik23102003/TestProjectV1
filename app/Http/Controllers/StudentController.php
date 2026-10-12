<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Material;
use App\Services\TelegramService;
use App\Services\SearchService;
use App\Services\PermissionService;
use App\Services\StorageService;
use App\Models\AuditLog;

class StudentController extends Controller
{
    protected TelegramService $telegramService;
    protected SearchService $searchService;
    protected PermissionService $permissionService;
    protected StorageService $storageService;

    public function __construct(
        TelegramService $telegramService,
        SearchService $searchService,
        PermissionService $permissionService,
        StorageService $storageService
    ) {
        $this->telegramService = $telegramService;
        $this->searchService = $searchService;
        $this->permissionService = $permissionService;
        $this->storageService = $storageService;
    }

    public function search(User $user, string $query, int $page = 1): void
    {
        $results = $this->searchService->search($user, $query, $page);

        if ($results->isEmpty()) {
            $this->telegramService->sendMessage($user->telegram_user_id, "❌ Natija topilmadi.");
            return;
        }

        $this->displaySearchResults($user->telegram_user_id, $results, $page);
    }

    private function displaySearchResults(int $telegramUserId, $results, int $page = 1): void
    {
        $text = "🔍 Qidiruv natijalari:\n\n";
        $buttons = [];

        foreach ($results as $material) {
            $text .= "📄 " . $material->title . "\n";
            $text .= "👨‍🏫 " . $material->teacher->full_name . "\n";
            $text .= "📝 " . \Illuminate\Support\Str::limit($material->description, 50) . "\n";
            $text .= "📦 " . formatBytes($material->size_bytes) . "\n\n";

            $buttons[] = [
                'text' => "⬇️ " . \Illuminate\Support\Str::limit($material->title, 15),
                'callback_data' => "download:{$material->id}"
            ];
        }

        if ($results->hasMorePages()) {
            $buttons[] = [
                'text' => "Keyingi ➡️",
                'callback_data' => "search:next:" . ($page + 1)
            ];
        }

        if ($page > 1) {
            $buttons[] = [
                'text' => "⬅️ Oldingi",
                'callback_data' => "search:prev:" . ($page - 1)
            ];
        }

        $this->telegramService->sendMessage($telegramUserId, $text, $buttons);
    }

    public function download(User $user, int $materialId): void
    {
        $material = Material::findOrFail($materialId);

        // Ruxsat tekshiruvi
        if (!$this->permissionService->canViewMaterial($user, $material)) {
            $this->telegramService->sendMessage($user->telegram_user_id, "❌ Ruxsat yo'q.");
            return;
        }

        // Katta fayl?
        $maxSize = config('materials.max_file_size_mb', 20) * 1024 * 1024;

        if ($material->size_bytes > $maxSize) {
            // Signed URL
            $url = $this->storageService->generateSignedUrl($material->storage_path);
            $buttons = [['text' => '⬇️ Yuklab olish', 'url' => $url]];

            $this->telegramService->sendMessage(
                $user->telegram_user_id,
                "📥 Fayl katta. Linkni bosing:",
                $buttons
            );
        } else {
            // Telegram orqali yuborish
            $this->sendFileViaBot($user, $material);
        }

        // Audit log
        AuditLog::create([
            'actor_id' => $user->id,
            'action_type' => 'material_downloaded',
            'subject_type' => 'material',
            'subject_id' => $material->id,
            'created_at' => now()
        ]);
    }

    private function sendFileViaBot(User $user, Material $material): void
    {
        try {
            $file = $this->storageService->getFileContent($material->storage_path);
            $this->telegramService->sendDocument(
                $user->telegram_user_id,
                $file,
                $material->original_filename
            );
        } catch (\Exception $e) {
            $this->telegramService->sendMessage(
                $user->telegram_user_id,
                "❌ Faylni jo'natishda xato: " . $e->getMessage()
            );
        }
    }

    public function listTeachers(User $user): void
    {
        $teachers = $user->teacherConnections()
            ->wherePivot('status', 'active')
            ->get();

        if ($teachers->isEmpty()) {
            $this->telegramService->sendMessage($user->telegram_user_id, "👨‍🏫 Ustozlaringiz yo'q.");
            return;
        }

        $text = "👨‍🏫 Sizning ustozlaringiz:\n\n";
        $buttons = [];

        foreach ($teachers as $teacher) {
            $text .= "👨‍🏫 " . $teacher->full_name . "\n\n";
            $buttons[] = [
                'text' => '📚 ' . $teacher->full_name,
                'callback_data' => "teacher:materials:{$teacher->id}"
            ];
        }

        $this->telegramService->sendMessage($user->telegram_user_id, $text, $buttons);
    }
}
