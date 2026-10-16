<?php

namespace App\Support;

final class LikeEscape
{
    /**
     * Foydalanuvchi kiritgan matndagi \ % _ belgilarini LIKE uchun escape qiladi
     * va "ichida bor" shaklidagi andoza qaytaradi.
     */
    public static function contains(string $value): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value).'%';
    }
}
