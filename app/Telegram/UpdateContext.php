<?php

namespace App\Telegram;

use App\Models\User;

class UpdateContext
{
    public int $updateId = 0;

    public int $chatId = 0;

    public int $telegramUserId = 0;

    public int $messageId = 0;

    public ?string $messageText = null;

    public ?string $callbackData = null;

    public ?string $fileId = null;

    public ?string $fileType = null;

    public ?User $user = null;

    public array $rawUpdate = [];

    public function __construct(array $update)
    {
        $this->rawUpdate = $update;
        $this->updateId = $update['update_id'] ?? 0;

        // Extract info
        if (isset($update['message'])) {
            $msg = $update['message'];
            $this->messageId = $msg['message_id'] ?? 0;
            $this->chatId = $msg['chat']['id'] ?? 0;
            $this->telegramUserId = $msg['from']['id'] ?? 0;
            $this->messageText = $msg['text'] ?? null;

            // File check
            if (isset($msg['document'])) {
                $this->fileId = $msg['document']['file_id'];
                $this->fileType = 'document';
            } elseif (isset($msg['photo']) && count($msg['photo']) > 0) {
                $this->fileId = $msg['photo'][count($msg['photo']) - 1]['file_id'];
                $this->fileType = 'photo';
            } elseif (isset($msg['audio'])) {
                $this->fileId = $msg['audio']['file_id'];
                $this->fileType = 'audio';
            } elseif (isset($msg['video'])) {
                $this->fileId = $msg['video']['file_id'];
                $this->fileType = 'video';
            }
        } elseif (isset($update['callback_query'])) {
            $cbq = $update['callback_query'];
            $this->messageId = $cbq['message']['message_id'] ?? 0;
            $this->chatId = $cbq['message']['chat']['id'] ?? 0;
            $this->telegramUserId = $cbq['from']['id'] ?? 0;
            $this->callbackData = $cbq['data'] ?? null;
        }

        // Load user
        $this->user = User::where('telegram_user_id', $this->telegramUserId)->first();
    }

    /**
     * Is message?
     */
    public function isMessage(): bool
    {
        return $this->messageText !== null || $this->fileId !== null;
    }

    /**
     * Is callback?
     */
    public function isCallback(): bool
    {
        return $this->callbackData !== null;
    }

    /**
     * Is file?
     */
    public function isFile(): bool
    {
        return $this->fileId !== null;
    }

    /**
     * Get user or create
     */
    public function getOrCreateUser(): User
    {
        if (! $this->user) {
            $this->user = User::create([
                'telegram_user_id' => $this->telegramUserId,
                'full_name' => '?',
                'role' => 'student',
                'status' => 'pending',
            ]);
        }

        return $this->user;
    }
}
