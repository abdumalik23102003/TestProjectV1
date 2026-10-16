<?php

namespace App\Services;

use App\Models\User;
use finfo;

class UploadValidator
{
    /**
     * @return array{error: string}|array{mime: string, size: int, checksum: string}
     */
    public function validate(string $filePath, User $teacher, string $originalName): array
    {
        if (! is_file($filePath)) {
            return ['error' => __('bot.file_not_found')];
        }

        $allowedTypes = (array) config('materials.allowed_types', []);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (! isset($allowedTypes[$ext])) {
            return ['error' => sprintf(
                __('bot.invalid_file_type'),
                implode(', ', array_keys($allowedTypes))
            )];
        }

        // Hajm
        $size = (int) filesize($filePath);
        $maxSizeMb = (int) config('materials.max_file_size_mb', 20);

        if ($size > $maxSizeMb * 1024 * 1024) {
            return ['error' => sprintf(__('bot.file_too_large'), $maxSizeMb)];
        }

        // Haqiqiy tarkibi (kengaytma emas, fayl ichidagi imzo) bo'yicha tur
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($filePath) ?: 'application/octet-stream';

        if (! in_array($mime, $allowedTypes[$ext], true)) {
            return ['error' => sprintf(
                __('bot.invalid_file_type'),
                implode(', ', array_keys($allowedTypes))
            )];
        }

        if (in_array($ext, (array) config('materials.zip_based_extensions', []), true)
            && ! $this->hasZipSignature($filePath)) {
            return ['error' => sprintf(
                __('bot.invalid_file_type'),
                implode(', ', array_keys($allowedTypes))
            )];
        }

        // Ustozning umumiy hajmi (kvota)
        $quotaGb = (int) config('materials.teacher_storage_quota_gb', 5);
        $usedBytes = (int) $teacher->materials()->sum('size_bytes');

        if ($usedBytes + $size > $quotaGb * 1024 * 1024 * 1024) {
            return ['error' => sprintf(__('bot.quota_exceeded'), $quotaGb)];
        }

        // Kunlik limit
        $dailyLimit = (int) config('materials.max_uploads_per_day', 100);
        $todayCount = $teacher->materials()
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($todayCount >= $dailyLimit) {
            return ['error' => sprintf(__('bot.daily_limit_exceeded'), $dailyLimit)];
        }

        return [
            'mime' => $mime,
            'size' => $size,
            'checksum' => hash_file('sha256', $filePath),
        ];
    }

    private function hasZipSignature(string $filePath): bool
    {
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            return false;
        }

        $head = fread($handle, 4);
        fclose($handle);

        return $head === "PK\x03\x04";
    }
}
