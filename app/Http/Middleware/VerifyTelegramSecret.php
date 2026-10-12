<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('telegram.webhook_secret');

        if (!$secret) {
            // No secret configured - allow
            return $next($request);
        }

        $headerSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (!hash_equals($secret, $headerSecret ?? '')) {
            return response('Unauthorized', 403);
        }

        return $next($request);
    }
}
