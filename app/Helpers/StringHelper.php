<?php

namespace App\Helpers;

class StringHelper
{
    /**
     * Truncate filename, preserve extension
     */
    public static function truncateFilename(string $filename, int $maxLength = 255): string
    {
        if (strlen($filename) <= $maxLength) {
            return $filename;
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $maxNameLength = $maxLength - strlen($ext) - 1;

        if ($maxNameLength < 1) {
            return substr($filename, 0, $maxLength);
        }

        return substr($name, 0, $maxNameLength) . '.' . $ext;
    }

    /**
     * Safe random token
     */
    public static function randomToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }
}
