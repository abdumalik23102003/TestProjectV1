<?php

namespace App\Http\Controllers;

use App\Helpers\FormatHelper;
use App\Models\Material;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\SearchService;
use App\Services\StorageService;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class StudentController extends Controller
{
    public function __construct(
        protected TelegramService $telegramService,
        protected SearchService $searchService,
        protected PermissionService $permissionService,
        protected StorageService $storageService,
        protected AuditService $auditService
    ) {}

    public function search(User $user, string $query, int $page = 1): void
    {
        $results = $this->searchService->search($user, $query, $page);

        if ($results->isEmpty()) {
            $this->telegramService->sendMessage(
                $user->telegram_user_id,
                __('bot.no_results'),
                [[['text' => __('bot.btn_back'), 'callback_data' => 'menu:main']]]
            );

            return;
        }

        $this->displaySearchResults($user->telegram_user_id, $results);
    }

    private function displaySearchResults(int $telegramUserId, $results): void
    {
        $text = "🔍 <b>Qidiruv natijalari</b>\n\n";
        $rows = [];

        foreach ($results as $material) {
            $text .= '📄 <b>'.e($material->title)."</b>\n";
            $text .= '👨‍🏫 '.e($material->teacher?->full_name ?? 'Noma’lum ustoz')."\n";

            if ($material->description) {
                $text .= '📝 '.e(Str::limit($material->description, 50))."\n";
            }

            $text .= '📦 '.FormatHelper::bytes((int) $material->size_bytes)."\n\n";

            $rows[] = [[
                'text' => '⬇️ '.Str::limit($material->title, 25),
                'callback_data' => 'student:download:'.$material->id,
            ]];
        }

        $nav = [];

        if ($results->currentPage() > 1) {
            $nav[] = [
                'text' => '⬅️ Oldingi',
                'callback_data' => 'student:search_prev:'.($results->currentPage() - 1),
            ];
        }

        if ($results->hasMorePages()) {
            $nav[] = [
                'text' => 'Keyingi ➡️',
                'callback_data' => 'student:search_next:'.($results->currentPage() + 1),
            ];
        }

        if ($nav !== []) {
            $rows[] = $nav;
        }

        $rows[] = [['text' => __('bot.btn_back'), 'callback_data' => 'menu:main']];

        $this->telegramService->sendMessage($telegramUserId, $text, $rows);
    }

    public function download(User $user, int $materialId): void
    {
        $material = Material::find($materialId);

        if (! $material) {
            $this->telegramService->sendMessage($user->telegram_user_id, __('bot.file_not_found'));

            return;
        }

        if (! $this->permissionService->canViewMaterial($user, $material)) {
            $this->telegramService->sendMessage($user->telegram_user_id, __('bot.access_denied'));

            return;
        }

        $maxSize = (int) config('materials.max_file_size_mb', 20) * 1024 * 1024;

        try {
            if ($material->size_bytes > $maxSize) {
                $url = $this->storageService->generateSignedUrl($material->storage_path);

                $this->telegramService->sendMessage(
                    $user->telegram_user_id,
                    '📥 Fayl katta. Linkni bosing:',
                    [[['text' => __('bot.btn_download'), 'url' => $url]]]
                );
                $sent = true;
            } else {
                $sent = $this->sendFileViaBot($user, $material);
            }
        } catch (Throwable $e) {
            Log::error('Material download failed', [
                'material_id' => $material->id,
                'error' => $e->getMessage(),
            ]);
            $sent = false;
        }

        if (! $sent) {
            $this->telegramService->sendMessage($user->telegram_user_id, __('bot.download_failed'));

            return;
        }

        $this->auditService->logFileDownloaded($user, $material->id, $material->original_filename);
    }

    private function sendFileViaBot(User $user, Material $material): bool
    {
        $file = $this->storageService->getFileContent($material->storage_path);

        if ($file === null) {
            Log::error('Material file missing on disk', ['material_id' => $material->id]);

            return false;
        }

        $result = $this->telegramService->sendDocument(
            $user->telegram_user_id,
            $file,
            $material->original_filename
        );

        return (bool) ($result['ok'] ?? false);
    }
}
