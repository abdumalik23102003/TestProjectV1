<?php

namespace App\Http\Controllers;

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
use App\Models\User;

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
        if ($session && ($session['current_step'] ?? null) === 'enter_name') {
            $this->handleRegistrationName($ctx, $client, $ctx->messageText);
            return;
        }

        // Handle callback
        if ($ctx->isCallback()) {
            $router = new Router();
            $router->register('role:*', \App\Telegram\Handlers\RoleHandler::class);
            $router->register('teacher:*', \App\Telegram\Handlers\TeacherHandler::class);
            $router->register('student:*', \App\Telegram\Handlers\StudentHandler::class);
            $router->register('admin:*', \App\Telegram\Handlers\AdminHandler::class);
            $router->register('help:*', \App\Telegram\Handlers\HelpHandler::class);

            $router->route($ctx, $client);
            return;
        }

        Log::info('Webhook: unhandled update');
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

        if ($isAdmin) {
            $client->sendMessage($ctx->chatId, __('bot.admin_menu'), $this->adminMenuButtons());
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
        return [
            [[\App\Telegram\Keyboard::button(__('bot.btn_approve_teachers'), 'admin:approve_teachers')]],
            [[\App\Telegram\Keyboard::button(__('bot.btn_users'), 'admin:users')]],
            [[\App\Telegram\Keyboard::button(__('bot.btn_audit'), 'admin:audit')]],
        ];
    }

    protected function notifyAdmins(TelegramClient $client, string $message): void
    {
        $adminIds = array_filter(array_map('intval', explode(',', config('telegram.admin_telegram_ids', ''))));

        foreach ($adminIds as $adminId) {
            $kb = [
                [\App\Telegram\Keyboard::button(__('bot.btn_approve_teachers'), 'admin:approve_teachers')]
            ];
            $client->sendMessage($adminId, $message, $kb);
        }
    }

    public function setWebhook(): \Illuminate\Http\JsonResponse
    {
        $client = new TelegramClient();
        $url = config('telegram.webhook_url');
        $secret = config('telegram.webhook_secret');

        $result = $client->setWebhook($url, $secret);

        return response()->json($result);
    }

    public function getWebhookInfo(): \Illuminate\Http\JsonResponse
    {
        $client = new TelegramClient();
        $result = $client->getWebhookInfo();

        return response()->json($result);
    }
}
