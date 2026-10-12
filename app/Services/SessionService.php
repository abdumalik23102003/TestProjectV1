<?php

namespace App\Services;

use App\Models\BotSession;
use Illuminate\Support\Carbon;

class SessionService
{
    public static function startWizard(int $telegramUserId, string $processType, string $initialStep, array $initialData = []): void
    {
        BotSession::updateOrCreate(
            ['telegram_user_id' => $telegramUserId, 'process_type' => $processType],
            [
                'current_step' => $initialStep,
                'payload' => json_encode($initialData),
                'expires_at' => now()->addMinutes(config('materials.wizard_session_timeout', 15))
            ]
        );
    }

    public static function getSession(int $telegramUserId, string $processType): ?array
    {
        $session = BotSession::where('telegram_user_id', $telegramUserId)
            ->where('process_type', $processType)
            ->first();

        return $session ? json_decode($session->payload, true) : null;
    }

    public static function updateSessionPayload(int $telegramUserId, string $processType, array $data): void
    {
        $session = BotSession::where('telegram_user_id', $telegramUserId)
            ->where('process_type', $processType)
            ->first();

        if ($session) {
            $session->update(['payload' => json_encode($data)]);
        }
    }

    public static function clearSession(int $telegramUserId, ?string $processType = null): void
    {
        $query = BotSession::where('telegram_user_id', $telegramUserId);
        
        if ($processType) {
            $query->where('process_type', $processType);
        }
        
        $query->delete();
    }
}
