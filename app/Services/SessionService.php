<?php

namespace App\Services;

use App\Models\BotSession;

class SessionService
{
    public static function startWizard(
        int $telegramUserId,
        string $processType,
        string $initialStep,
        array $initialData = []
    ): void {
        BotSession::updateOrCreate(
            [
                'telegram_user_id' => $telegramUserId,
                'process_type' => $processType,
            ],
            [
                'current_step' => $initialStep,
                'payload' => json_encode(
                    $initialData,
                    JSON_UNESCAPED_UNICODE
                ),
                'expires_at' => now()->addMinutes(
                    (int) config('materials.wizard_session_timeout', 15)
                ),
            ]
        );
    }

    public static function getSession(
        int $telegramUserId,
        string $processType
    ): ?array {
        $session = BotSession::where('telegram_user_id', $telegramUserId)
            ->where('process_type', $processType)
            ->first();

        if (! $session) {
            return null;
        }

        // Muddati tugagan sessiyani o'chiramiz.
        if (
            $session->expires_at &&
            now()->greaterThanOrEqualTo($session->expires_at)
        ) {
            $session->delete();

            return null;
        }

        $payload = json_decode($session->payload, true) ?? [];
        $payload['current_step'] = $session->current_step;

        return $payload;
    }

    public static function updateSessionPayload(
        int $telegramUserId,
        string $processType,
        array $data
    ): void {
        $session = BotSession::where('telegram_user_id', $telegramUserId)
            ->where('process_type', $processType)
            ->first();

        if (! $session) {
            return;
        }

        unset($data['current_step']);

        $session->update([
            'payload' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'expires_at' => now()->addMinutes(
                (int) config('materials.wizard_session_timeout', 15)
            ),
        ]);
    }

    // Ma'lumotlarni va joriy bosqichni birgalikda yangilaydi.
    public static function updateWizard(
        int $telegramUserId,
        string $processType,
        string $nextStep,
        array $data
    ): void {
        $session = BotSession::where('telegram_user_id', $telegramUserId)
            ->where('process_type', $processType)
            ->first();

        if (! $session) {
            return;
        }

        $payload = json_decode($session->payload, true) ?? [];

        unset($data['current_step']);

        $payload = array_merge($payload, $data);

        $session->update([
            'current_step' => $nextStep,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'expires_at' => now()->addMinutes(
                (int) config('materials.wizard_session_timeout', 15)
            ),
        ]);
    }

    public static function clearSession(
        int $telegramUserId,
        ?string $processType = null
    ): void {
        $query = BotSession::where(
            'telegram_user_id',
            $telegramUserId
        );

        if ($processType) {
            $query->where('process_type', $processType);
        }

        $query->delete();
    }
}
