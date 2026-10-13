<?php

namespace App\Http\Controllers;

use App\Telegram\Handlers\MenuHandler;
use App\Telegram\Menus;
use App\Telegram\Keyboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Telegram\UpdateContext;
use App\Telegram\TelegramClient;
use App\Telegram\Router;
use App\Telegram\Handlers\StartHandler;
use App\Helpers\HtmlHelper;
use App\Helpers\StringHelper;
use App\Services\SessionService;
use App\Services\SearchService;
use App\Services\AuditService;
use App\Models\User;
use Throwable;

class TelegramWebhookController extends Controller
{
    public function handleWebhook(Request $request): \Illuminate\Http\Response
    {
        $update = $request->json()->all();

        if (empty($update)) {
            Log::warning('Empty webhook update');
            return response('', 200);
        }

        $updateId = $update['update_id'] ?? null;

        if (!$updateId) {
            return response('', 200);
        }

        // Deduplication: skip if already processed
        $cacheKey = "telegram_update_{$updateId}";
        if (Cache::has($cacheKey)) {
            Log::info("Webhook: duplicate update_id [{$updateId}]");
            return response('', 200);
        }

        Cache::put($cacheKey, true, now()->addDay()); // Keep 1 day

        try {
            // Create context
            $ctx = new UpdateContext($update);

            // Process
            $this->process($ctx);
        } catch (\Exception $e) {
            Log::error('Webhook processing error: ' . $e->getMessage());
        }

        return response('', 200);
    }

    protected function process(UpdateContext $ctx): void
    {
        $client = new TelegramClient();

        // Handle /start command
        if ($ctx->messageText && str_starts_with($ctx->messageText, '/start')) {
            $param = trim(str_replace('/start', '', $ctx->messageText));
            $handler = new StartHandler();
            $handler->handle($ctx, $client, ['start', $ctx->telegramUserId, $param]);
            return;
        }

        // Handle /help
        if ($ctx->messageText === '/help') {
            $client->sendMessage($ctx->chatId, __('bot.help_text'));
            return;
        }

        // Handle registration input (user sending name)
        $session = SessionService::getSession($ctx->telegramUserId, 'registration');
        Log::info('Registration session check', [
            'telegram_user_id' => $ctx->telegramUserId,
            'message_text' => $ctx->messageText,
            'session' => $session,
        ]);
        if ($session && ($session['current_step'] ?? null) === 'enter_name') {
            $this->handleRegistrationName($ctx, $client, $ctx->messageText);
            return;
        }


        // Handle incoming files for teacher uploads
        if ($ctx->isFile()) {
            $client = new TelegramClient();

            $user = User::where(
                'telegram_user_id',
                $ctx->telegramUserId
            )->first();

            if (!$user || $user->role !== 'teacher' || $user->status !== 'active') {
                $client->sendMessage(
                    $ctx->chatId,
                    '❌ Fayl yuborish uchun tasdiqlangan ustoz bo‘lishingiz kerak.'
                );
                return;
            }

            $session = SessionService::getSession(
                $ctx->telegramUserId,
                'upload'
            );

            if (!$session || ($session['current_step'] ?? '') !== 'awaiting_file') {
                $client->sendMessage(
                    $ctx->chatId,
                    '❌ Yangi fayl yuklash uchun avval 📤 Fayl yuklash tugmasini bosing. Metadata kiritayotgan bo‘lsangiz, avval uni yakunlang yoki bekor qiling.'
                );
                return;
            }

            $fileInfo = $client->getFile($ctx->fileId);

            if (!($fileInfo['ok'] ?? false)) {
                $client->sendMessage(
                    $ctx->chatId,
                    '❌ Telegram fayl ma’lumotlarini olishda xatolik yuz berdi.'
                );
                return;
            }

            $filePath = $fileInfo['result']['file_path'] ?? null;

            if (!$filePath) {
                $client->sendMessage(
                    $ctx->chatId,
                    '❌ Fayl manzili topilmadi.'
                );
                return;
            }

            $content = $client->downloadFile($filePath);

            if ($content === null) {
                $client->sendMessage(
                    $ctx->chatId,
                    '❌ Faylni yuklab olib bo‘lmadi. Qayta urinib ko‘ring.'
                );
                return;
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'telegram_upload_');

            if ($tempPath === false || file_put_contents($tempPath, $content) === false) {
                $client->sendMessage(
                    $ctx->chatId,
                    '❌ Vaqtinchalik faylni yaratib bo‘lmadi.'
                );

                if ($tempPath !== false) {
                    @unlink($tempPath);
                }

                return;
            }

            try {
                $filename = $ctx->rawUpdate['message']['document']['file_name']
                    ?? basename($filePath);

                app(TeacherController::class)
                    ->uploadFile($user, [
                        'file' => $tempPath,
                        'filename' => $filename,
                    ]);
            } catch (Throwable $e) {
                Log::error(
                    'Teacher file upload failed: ' . $e->getMessage()
                );

                $client->sendMessage(
                    $ctx->chatId,
                    '❌ Faylni saqlashda xatolik yuz berdi.'
                );
            } finally {
                @unlink($tempPath);
            }

            return;
        }
// Handle teacher upload metadata text
        if ($ctx->messageText !== null) {
            $uploadSession = SessionService::getSession(
                $ctx->telegramUserId,
                'upload'
            );

            $uploadSteps = [
                'enter_title',
                'enter_description',
                'enter_category',
                'enter_visibility',
                'confirm',
            ];

            if (
                $uploadSession &&
                in_array(
                    $uploadSession['current_step'] ?? '',
                    $uploadSteps,
                    true
                )
            ) {
                $user = User::where(
                    'telegram_user_id',
                    $ctx->telegramUserId
                )->first();

                if (
                    !$user ||
                    $user->role !== 'teacher' ||
                    $user->status !== 'active'
                ) {
                    SessionService::clearSession(
                        $ctx->telegramUserId,
                        'upload'
                    );

                    $client->sendMessage(
                        $ctx->chatId,
                        '❌ Faol ustoz akkaunti talab qilinadi.'
                    );
                    return;
                }

                app(TeacherController::class)->handleUploadText(
                    $ctx->telegramUserId,
                    $ctx->messageText
                );

                return;
            }
        }
// Handle material search input
        $searchSession = SessionService::getSession(
            $ctx->telegramUserId,
            'search'
        );

        if (
            $ctx->messageText !== null &&
            $searchSession &&
            ($searchSession['current_step'] ?? null) === 'enter_query'
        ) {
            $query = trim($ctx->messageText);

            if (strlen($query) < 2) {
                $client->sendMessage(
                    $ctx->chatId,
                    __('bot.search_prompt')
                );
                return;
            }

            if (!$ctx->user || $ctx->user->status !== 'active') {
                SessionService::clearSession(
                    $ctx->telegramUserId,
                    'search'
                );

                $client->sendMessage(
                    $ctx->chatId,
                    __('bot.unknown_action')
                );
                return;
            }

            try {
                app(\App\Http\Controllers\StudentController::class)
                    ->search($ctx->user, $query);

                SessionService::clearSession(
                    $ctx->telegramUserId,
                    'search'
                );
            } catch (Throwable $e) {
                Log::error('Material search failed', [
                    'telegram_user_id' => $ctx->telegramUserId,
                    'error' => $e->getMessage(),
                ]);

                $client->sendMessage(
                    $ctx->chatId,
                    __('bot.no_results')
                );
            }

            return;
        }
        // Handle callback
        if ($ctx->isCallback()) {
            $router = new Router();
            $router->register('role:*', RoleHandler::class);
            $router->register('menu:*', MenuHandler::class);
            $router->register('teacher:*', TeacherHandler::class);
            $router->register('upload:*', TeacherHandler::class);
            $router->register('student:*', StudentHandler::class);
            $router->register('admin:*', AdminHandler::class);
            $router->register('help:*', HelpHandler::class);
            $router->register('confirm:*', MenuHandler::class);
            $router->register('approve:*', MenuHandler::class);

            $router->route($ctx, $client);
            return;
        }

        Log::info('Webhook: unhandled update', [
            'message_text' => $ctx->messageText,
            'callback_data' => $ctx->callbackData,
            'telegram_user_id' => $ctx->telegramUserId,
            'chat_id' => $ctx->chatId,
            'is_message' => $ctx->isMessage(),
            'is_callback' => $ctx->isCallback(),
        ]);
    }

    protected function handleRegistrationName(UpdateContext $ctx, TelegramClient $client, ?string $name): void
    {
        $name = trim($name ?? '');

        if (strlen($name) < 2 || strlen($name) > 100) {
            $client->sendMessage($ctx->chatId, __('bot.invalid_name'));
            return;
        }

        // Escape HTML
        $name = HtmlHelper::escape($name);

        // Get user or create
        $user = $ctx->getOrCreateUser();

        // Get role from session
        $session = SessionService::getSession($ctx->telegramUserId, 'registration');

        $role = $session['role'] ?? 'student';

        // Check if user is admin
        $adminIds = array_filter(array_map('intval', explode(',', config('telegram.admin_telegram_ids', ''))));
        $isAdmin = in_array($ctx->telegramUserId, $adminIds);

        // Update user
        $user->update([
            'full_name' => $name,
            'role' => $isAdmin ? 'admin' : $role,
            'status' => ($isAdmin || $role === 'student') ? 'active' : 'pending',
        ]);

        if ($role === 'teacher' && !$isAdmin) {
            $user->teacher_approval_token = StringHelper::randomToken();
            $user->save();
        }

        SessionService::clearSession($ctx->telegramUserId, 'registration');

        // Log registration
        app(AuditService::class)->logRegistration($user, $isAdmin ? 'admin' : $role);

        if ($isAdmin) {
            $menu = Menus::adminMain();
            $client->sendMessage($ctx->chatId, $menu['text'], $menu['buttons']);
        } elseif ($role === 'teacher') {
            $client->sendMessage($ctx->chatId, __('bot.waiting_approval'));
            // Notify admins
            $this->notifyAdmins($client, "🆕 Yangi ustoz: <b>{$name}</b>");
        } else {
            $client->sendMessage($ctx->chatId, __('bot.registration_complete'));
            // Show student menu
            app(StartHandler::class)->showMenu($ctx, $client, $user);
        }
    }

    protected function adminMenuButtons(): array
    {
        // Deprecated - use Menus::adminMain() instead
        return Menus::adminMain()['buttons'];
    }

    protected function notifyAdmins(TelegramClient $client, string $message): void
    {
        $adminIds = array_filter(array_map('intval', explode(',', config('telegram.admin_telegram_ids', ''))));

        foreach ($adminIds as $adminId) {
            $kb = [
                [Keyboard::button(__('bot.btn_approve_teachers'), 'admin:approve_teachers')]
            ];
            $client->sendMessage($adminId, $message, $kb);
        }
    }

    public function setWebhook(): JsonResponse
    {
        $client = new TelegramClient();
        $url = config('telegram.webhook_url');
        $secret = config('telegram.webhook_secret');

        $result = $client->setWebhook($url, $secret);

        return response()->json($result);
    }

    public function getWebhookInfo(): JsonResponse
    {
        $client = new TelegramClient();
        $result = $client->getWebhookInfo();

        return response()->json($result);
    }
}
