<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
    protected string $disk = 'private_materials';

    /**
     * Faylni vaqtinchalik ("pending_") joyga saqlaydi.
     * Diskdagi nom UUID asosida; foydalanuvchi bergan nom faqat ko'rsatish uchun qaytariladi.
     */
    public function storeTemporaryFile($file, string $originalName): array
    {
        $displayName = $this->sanitizeFilename($originalName);
        $path = now()->format('Y/m/d').'/pending_'.Str::uuid().$this->extensionSuffix($displayName);

        $stream = fopen($file, 'rb');

        if ($stream === false) {
            throw new \RuntimeException('Could not read temporary upload file.');
        }

        try {
            $disk = Storage::disk($this->disk);

            if (! $disk->put($path, $stream)) {
                throw new \RuntimeException('Could not save temporary upload file.');
            }
        } finally {
            fclose($stream);
        }

        return [
            'path' => $path,
            'size' => (int) filesize($file),
            'mime' => $disk->mimeType($path),
            'checksum' => hash_file('sha256', $file),
            'original_name' => $displayName,
        ];
    }

    /**
     * Vaqtinchalik faylni doimiy joyga NUSXALAYDI (vaqtinchalik fayl o'chirilmaydi:
     * chaqiruvchi bazaga yozish muvaffaqiyatli bo'lgandan keyin uni o'zi o'chiradi).
     */
    public function promoteToPermanent(string $tempPath): string
    {
        $disk = Storage::disk($this->disk);

        if (! $disk->exists($tempPath)) {
            throw new \RuntimeException('Temporary file not found.');
        }

        $newPath = now()->format('Y/m/d').'/'.Str::uuid().$this->extensionSuffix($tempPath);

        if (! $disk->copy($tempPath, $newPath)) {
            throw new \RuntimeException('Could not copy file to permanent storage.');
        }

        return $newPath;
    }

    /**
     * Ko'rsatiladigan fayl nomini tozalaydi: har qanday alifbodagi harflar,
     * raqamlar, nuqta, tire, pastki chiziq va probel qoladi; yo'l ajratgichlari yo'qoladi.
     */
    public function sanitizeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[^\p{L}\p{M}\p{N}._ -]+/u', '_', $filename) ?? '';
        $filename = preg_replace('/\.{2,}/', '.', $filename) ?? '';
        $filename = trim($filename, ' .');

        if ($filename === '') {
            return 'document';
        }

        if (mb_strlen($filename) > 150) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $base = mb_substr(pathinfo($filename, PATHINFO_FILENAME), 0, 150 - mb_strlen($ext) - 1);
            $filename = $ext !== '' ? $base.'.'.$ext : $base;
        }

        return $filename;
    }

    public function generateSignedUrl(string $storagePath, ?int $expiryMinutes = null): string
    {
        $expiryMinutes ??= (int) config('materials.signed_url_expiry_minutes', 10);

        return Storage::disk($this->disk)->temporaryUrl($storagePath, now()->addMinutes($expiryMinutes));
    }

    public function deleteFile(string $storagePath): bool
    {
        return Storage::disk($this->disk)->delete($storagePath);
    }

    public function fileExists(string $storagePath): bool
    {
        return Storage::disk($this->disk)->exists($storagePath);
    }

    public function getFileContent(string $storagePath)
    {
        return Storage::disk($this->disk)->get($storagePath);
    }

    public function getFileSize(string $storagePath): int
    {
        return Storage::disk($this->disk)->size($storagePath);
    }

    public function getMimeType(string $storagePath): string
    {
        return Storage::disk($this->disk)->mimeType($storagePath);
    }

    private function extensionSuffix(string $name): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return preg_match('/^[a-z0-9]{1,10}$/', $ext) ? '.'.$ext : '';
    }
}
