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
        if (! $ctx->isCallback()) {
            Log::warning('Router: not a callback');

            return;
        }

        $parts = explode(':', $ctx->callbackData, 4);
        $pattern = $parts[0].':'.($parts[1] ?? 'index');
        $handler = $this->routes[$pattern]
            ?? $this->routes[explode(':', $pattern)[0].':*']
            ?? null;
        Log::info('Router handler diagnostic', [
            'pattern' => $pattern,
            'handler' => $handler,
            'handler_exists' => $handler && class_exists($handler),
        ]);
        if (! $handler) {
            Log::warning("Router: unknown pattern [{$pattern}]");
            $client->answerCallbackQuery($this->getCallbackId($ctx), __('bot.unknown_action'));

            return;
        }

        try {
            app($handler)->handle($ctx, $client, $parts);
        } catch (\Throwable $e) {
            Log::error("Router handler error [{$pattern}]", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'exception' => get_class($e),
            ]);

            $client->answerCallbackQuery(
                $this->getCallbackId($ctx),
                __('bot.unknown_error')
            );
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
