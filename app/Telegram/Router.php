<?php

namespace App\Telegram;

use Illuminate\Support\Facades\Log;

class Router
{
    protected array $routes = [];

    /**
     * Register route
     * Format: "namespace:action" => HandlerClass::class
     */
    public function register(string $pattern, string $handler): self
    {
        $this->routes[$pattern] = $handler;
        return $this;
    }

    /**
     * Route callback_data to handler
     * Format: "ns:action:id:extra"
     */
    public function route(UpdateContext $ctx, TelegramClient $client): void
    {
        if (!$ctx->isCallback()) {
            Log::warning('Router: not a callback');
            return;
        }

        $parts = explode(':', $ctx->callbackData, 4);
        $pattern = $parts[0] . ':' . ($parts[1] ?? 'index');
        $handler = $this->routes[$pattern] ?? null;

        if (!$handler) {
            Log::warning("Router: unknown pattern [{$pattern}]");
            $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.unknown_action'));
            return;
        }

        try {
            app($handler)->handle($ctx, $client, $parts);
        } catch (\Exception $e) {
            Log::error("Router handler error [{$pattern}]: " . $e->getMessage());
            $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.unknown_error'));
        }
    }

    /**
     * Get callback query ID
     */
    protected function getCallbackId(UpdateContext $ctx): string
    {
        return $ctx->rawUpdate['callback_query']['id'] ?? '';
    }
}
