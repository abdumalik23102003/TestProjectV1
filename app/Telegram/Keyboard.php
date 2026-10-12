<?php

namespace App\Telegram;

class Keyboard
{
    /**
     * Inline keyboard builder
     * Usage: Keyboard::inline([[['text' => '...', 'callback_data' => '...']], ...])
     */
    public static function inline(array $buttons): array
    {
        return ['inline_keyboard' => $buttons];
    }

    /**
     * Reply keyboard builder
     */
    public static function reply(array $buttons, bool $oneTime = false, bool $resize = true): array
    {
        return [
            'keyboard' => $buttons,
            'one_time_keyboard' => $oneTime,
            'resize_keyboard' => $resize,
            'selective' => false,
        ];
    }

    /**
     * Button (helper)
     */
    public static function button(string $text, string $data = null, string $url = null): array
    {
        $btn = ['text' => $text];

        if ($data !== null) {
            $btn['callback_data'] = $data;
        }

        if ($url !== null) {
            $btn['url'] = $url;
        }

        return $btn;
    }

    /**
     * Create rows (2-column layout)
     */
    public static function row(...$buttons): array
    {
        return $buttons;
    }

    /**
     * Empty inline keyboard (removes keyboard)
     */
    public static function removeInline(): array
    {
        return ['inline_keyboard' => []];
    }
}
