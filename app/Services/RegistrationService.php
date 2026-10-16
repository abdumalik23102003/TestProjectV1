<?php

namespace App\Services;

use App\Helpers\StringHelper;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegistrationService
{
    /** Ro'yxatdan o'tmagan foydalanuvchining vaqtinchalik ismi. */
    public const PLACEHOLDER_NAME = '?';

    /**
     * Foydalanuvchi hali ro'yxatdan o'tmagan (yoki yarim qoldirgan) bo'lsa true.
     * Bloklangan, rad etilgan yoki faol foydalanuvchi bu oqimga qayta kira olmaydi.
     */
    public function needsRegistration(?User $user): bool
    {
        if (! $user) {
            return true;
        }

        return $user->status === 'pending'
            && $user->role === 'student'
            && $user->full_name === self::PLACEHOLDER_NAME;
    }

    public function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    }

    /**
     * 2-100 belgi; harf, raqam, probel, nuqta, tire va apostrof ruxsat etiladi.
     * HTML uchun xavfli belgilar (< > & ") kirmaydi, shuning uchun escape talab qilinmaydi.
     */
    public function isValidName(string $name): bool
    {
        $length = mb_strlen($name);

        if ($length < 2 || $length > 100) {
            return false;
        }

        return (bool) preg_match('/^[\p{L}\p{N}][\p{L}\p{M}\p{N} .\'’‘ʻʼ-]*$/u', $name);
    }

    /**
     * @return int[]
     */
    public function adminTelegramIds(): array
    {
        return array_values(array_filter(
            array_map('intval', explode(',', (string) config('telegram.admin_telegram_ids', '')))
        ));
    }

    public function isAdminTelegramId(int $telegramUserId): bool
    {
        return in_array($telegramUserId, $this->adminTelegramIds(), true);
    }

    /**
     * Foydalanuvchini yaratadi (yoki vaqtinchalik yozuvni to'ldiradi).
     * Ustoz bo'lsa — tasdiq kutadi, o'quvchi va admin — darhol faol.
     */
    public function register(int $telegramUserId, string $name, string $role): User
    {
        $isAdmin = $this->isAdminTelegramId($telegramUserId);
        $role = $role === 'teacher' ? 'teacher' : 'student';

        return DB::transaction(function () use ($telegramUserId, $name, $role, $isAdmin) {
            $user = User::where('telegram_user_id', $telegramUserId)->lockForUpdate()->first()
                ?? new User(['telegram_user_id' => $telegramUserId]);

            $user->fill([
                'full_name' => $name,
                'role' => $isAdmin ? 'admin' : $role,
                'status' => ($isAdmin || $role === 'student') ? 'active' : 'pending',
            ]);

            $user->save();

            return $user;
        });
    }

    /**
     * Ustozning taklif tokeni (havola uchun).
     */
    public function newInviteToken(): string
    {
        return StringHelper::randomToken(32);
    }
}
