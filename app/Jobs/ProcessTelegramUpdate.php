<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Material;
use App\Services\TelegramService;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\AdminController;
use App\Services\SessionService;
use App\Models\AuditLog;

class ProcessTelegramUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $update;
    protected TelegramService $telegramService;

    public function __construct(array $update)
    {
        $this->update = $update;
        $this->telegramService = app(TelegramService::class);
    }

    public function handle(): void
    {
        if (isset($this->update['message'])) {
            $this->handleMessage();
        } elseif (isset($this->update['callback_query'])) {
            $this->handleCallbackQuery();
        }
    }

    private function handleMessage(): void
    {
        $message = $this->update['message'];
        $userId = $message['from']['id'];
        $text = $message['text'] ?? '';

        // User mavjudmi?
        $user = User::where('telegram_user_id', $userId)->first();

        // Blocked user check
        if ($user && $user->status === 'blocked') {
            return;
        }

        // /start command
        if (str_starts_with($text, '/start')) {
            $param = str_replace('/start ', '', $text);
            app(AuthController::class)->handleStart($userId, $param ?: null);
            return;
        }

        if (!$user) {
            app(AuthController::class)->handleStart($userId);
            return;
        }

        // Aktiv session tekshirish
        $session = SessionService::getSession($userId, 'upload');

        if ($session) {
            $currentStep = $session['current_step'] ?? 'enter_title';

            match ($currentStep) {
                'enter_title' => app(TeacherController::class)->handleMetadataStep($userId, 'title', $text),
                'enter_description' => app(TeacherController::class)->handleMetadataStep($userId, 'description', $text),
                'enter_category' => app(TeacherController::class)->handleMetadataStep($userId, 'category_id', $text),
                'enter_visibility' => app(TeacherController::class)->handleMetadataStep($userId, 'visibility', $text),
                default => null
            };

            return;
        }

        // Registration session
        $regSession = SessionService::getSession($userId, 'registration');
        if ($regSession && $regSession['role']) {
            app(AuthController::class)->handleNameInput($userId, $text);
            return;
        }
    }

    private function handleCallbackQuery(): void
    {
        $callback = $this->update['callback_query'];
        $userId = $callback['from']['id'];
        $data = $callback['data'];
        $callbackId = $callback['id'];
        $messageId = $callback['message']['message_id'] ?? null;

        $user = User::where('telegram_user_id', $userId)->first();

        if (!$user || $user->status === 'blocked') {
            $this->telegramService->answerCallbackQuery($callbackId, "❌ Xato", false);
            return;
        }

        // Callback parsing
        $parts = explode(':', $data);
        $action = $parts[0] ?? '';
        $param1 = $parts[1] ?? null;
        $param2 = $parts[2] ?? null;

        switch ($action) {
            case 'role':
                app(AuthController::class)->handleRoleSelection($userId, $param1);
                break;

            case 'teacher':
                $controller = app(TeacherController::class);

                switch ($param1) {
                    case 'upload':
                        $controller->uploadFile($user, []);
                        break;

                    case 'materials':
                        $controller->listMaterials($user);
                        break;
                }
                break;

            case 'student':
                $controller = app(StudentController::class);

                switch ($param1) {
                    case 'search':
                        $controller->search($user, '');
                        break;

                    case 'teachers':
                        $controller->listTeachers($user);
                        break;
                }
                break;

            case 'download':
                app(StudentController::class)->download($user, (int) $param1);
                break;

            case 'upload':
                $controller = app(TeacherController::class);

                switch ($param1) {
                    case 'save':
                        $controller->confirmUpload($userId);
                        break;

                    case 'cancel':
                        $controller->cancelWizard($userId);
                        break;

                    case 'edit':
                    case 'back':
                        $controller->editUpload($userId);
                        break;

                    default:
                        $this->telegramService->answerCallbackQuery(
                            $callbackId,
                            "Noma'lum yuklash amali",
                            false
                        );
                        break;
                }
                break;

            default:
                $this->telegramService->answerCallbackQuery(
                    $callbackId,
                    "Noma'lum amal",
                    false
                );
                break;
        }


        $this->telegramService->answerCallbackQuery($callbackId, "✅ OK", false);
    }
}
