<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('telegram.webhook_secret');

        if ($secret === '') {
            // Secret sozlanmagan: faqat local/testing muhitda ruxsat beramiz.
            if (app()->environment(['local', 'testing'])) {
                return $next($request);
            }

            return response('Webhook secret is not configured', 403);
        }

        $headerSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if (! hash_equals($secret, $headerSecret)) {
            return response('Unauthorized', 403);
        }

        return $next($request);
    }
}
