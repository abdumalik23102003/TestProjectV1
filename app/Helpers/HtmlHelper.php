<?php

namespace App\Helpers;

class HtmlHelper
{
    /**
     * HTML-escape user input
     */
    public static function escape(?string $text): string
    {
        return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize and truncate text
     */
    public static function sanitize(string $text, int $maxLength = 255): string
    {
        $text = trim($text);
        $text = preg_replace('/\s+/', ' ', $text); // normalize whitespace
        $text = self::escape($text);
        return substr($text, 0, $maxLength);
    }
}
