<?php

namespace App\Telegram;

use App\Models\User;

/**
 * Centralized menu definitions for all roles
 * Returns structured menu with text and inline keyboard
 */
class Menus
{
    /**
     * Main admin menu
     */
    public static function adminMain(): array
    {
        return [
            'text' => __('bot.admin_menu'),
            'buttons' => [
                [Keyboard::button(__('bot.btn_approve_teachers'), 'menu:approve_teachers')],
                [Keyboard::button(__('bot.btn_users'), 'menu:users')],
                [Keyboard::button(__('bot.btn_audit'), 'menu:audit')],
                [Keyboard::button(__('bot.btn_help'), 'menu:help')],
            ],
        ];
    }

    /**
     * Main teacher menu
     */
    public static function teacherMain(): array
    {
        return [
            'text' => __('bot.teacher_menu'),
            'buttons' => [
                [Keyboard::button(__('bot.btn_upload'), 'teacher:upload')],
                [Keyboard::button(__('bot.btn_my_files'), 'teacher:files')],
                [Keyboard::button(__('bot.btn_search'), 'student:search')],
                [Keyboard::button(__('bot.btn_profile'), 'menu:profile')],
                [Keyboard::button(__('bot.btn_help'), 'menu:help')],
            ],
        ];
    }

    /**
     * Main student menu
     */
    public static function studentMain(): array
    {
        return [
            'text' => __('bot.student_menu'),
            'buttons' => [
                [Keyboard::button(__('bot.btn_search'), 'student:search')],
                [Keyboard::button(__('bot.btn_my_teachers'), 'student:teachers')],
                [Keyboard::button(__('bot.btn_recent'), 'student:recent')],
                [Keyboard::button(__('bot.btn_profile'), 'menu:profile')],
                [Keyboard::button(__('bot.btn_help'), 'menu:help')],
            ],
        ];
    }

    /**
     * Get main menu for a user
     */
    public static function main(User $user): array
    {
        return match ($user->role) {
            'admin' => self::adminMain(),
            'teacher' => self::teacherMain(),
            default => self::studentMain(),
        };
    }

    /**
     * Confirm/Cancel menu
     */
    public static function confirm(string $prompt = ''): array
    {
        $text = !empty($prompt) ? $prompt : __('bot.select_option');

        return [
            'text' => $text,
            'buttons' => [
                [
                    Keyboard::button(__('bot.btn_confirm'), 'confirm:yes'),
                    Keyboard::button(__('bot.btn_cancel'), 'confirm:no'),
                ],
            ],
        ];
    }

    /**
     * Approve/Reject menu
     */
    public static function approveReject(string $prompt = ''): array
    {
        $text = !empty($prompt) ? $prompt : __('bot.select_option');

        return [
            'text' => $text,
            'buttons' => [
                [
                    Keyboard::button(__('bot.btn_confirm'), 'approve:yes'),
                    Keyboard::button(__('bot.btn_reject'), 'approve:no'),
                ],
            ],
        ];
    }

    /**
     * Back button menu
     */
    public static function back(string $prompt = ''): array
    {
        $text = !empty($prompt) ? $prompt : __('bot.back_to_menu');

        return [
            'text' => $text,
            'buttons' => [
                [Keyboard::button(__('bot.btn_back'), 'menu:main')],
            ],
        ];
    }

    /**
     * File actions menu (for a specific file)
     */
    public static function fileActions(string $fileId): array
    {
        return [
            'text' => __('bot.select_option'),
            'buttons' => [
                [
                    Keyboard::button(__('bot.btn_download'), "file:download:{$fileId}"),
                    Keyboard::button(__('bot.btn_details'), "file:details:{$fileId}"),
                ],
                [
                    Keyboard::button(__('bot.btn_edit'), "file:edit:{$fileId}"),
                    Keyboard::button(__('bot.btn_delete'), "file:delete:{$fileId}"),
                ],
                [Keyboard::button(__('bot.btn_back'), 'menu:main')],
            ],
        ];
    }

    /**
     * Category selection (for file upload)
     */
    public static function categorySelect(): array
    {
        $categories = config('materials.categories', [
            'matematika',
            'tarix',
            'adabiyot',
            'biologiya',
            'kimyo',
            'fizika',
            ' inglizcha',
        ]);

        $buttons = [];
        foreach (array_chunk($categories, 2) as $chunk) {
            $row = [];
            foreach ($chunk as $cat) {
                $row[] = Keyboard::button(ucfirst($cat), "upload:cat:{$cat}");
            }
            $buttons[] = $row;
        }

        $buttons[] = [
            Keyboard::button(__('bot.btn_skip'), 'upload:cat:none'),
        ];
        $buttons[] = [Keyboard::button(__('bot.btn_back'), 'menu:main')];

        return [
            'text' => __('bot.select_category'),
            'buttons' => $buttons,
        ];
    }

    /**
     * Visibility selection (for file upload)
     */
    public static function visibilitySelect(): array
    {
        return [
            'text' => __('bot.select_visibility'),
            'buttons' => [
                [Keyboard::button('🌍 ' . __('bot.status_public'), 'upload:vis:public')],
                [Keyboard::button('🔒 ' . __('bot.status_private'), 'upload:vis:private')],
                [Keyboard::button(__('bot.btn_back'), 'menu:main')],
            ],
        ];
    }

    /**
     * Block/Unblock user menu
     */
    public static function blockActions(): array
    {
        return [
            'text' => __('bot.select_option'),
            'buttons' => [
                [Keyboard::button(__('bot.btn_block'), 'admin:block')],
                [Keyboard::button(__('bot.btn_unblock'), 'admin:unblock')],
                [Keyboard::button(__('bot.btn_back'), 'menu:main')],
            ],
        ];
    }
}
