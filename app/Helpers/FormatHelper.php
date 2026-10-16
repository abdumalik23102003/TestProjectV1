<?php

namespace App\Helpers;

class FormatHelper
{
    /**
     * Baytlarni o'qilishi oson ko'rinishga o'tkazadi: 2048 -> "2 KB".
     */
    public static function bytes(int|float $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), $precision).' '.$units[$power];
    }
}
