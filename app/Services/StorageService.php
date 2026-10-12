<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
    protected string $disk = 'private_materials';

    public function storeTemporaryFile($file, string $originalName): array
    {
        $sanitizedName = $this->sanitizeFilename($originalName);
        $path = 'temp/' . Str::uuid() . '/' . $sanitizedName;

        Storage::disk($this->disk)->put($path, file_get_contents($file));

        $size = Storage::disk($this->disk)->size($path);
        $mime = Storage::disk($this->disk)->mimeType($path);
        $checksum = md5_file($file);

        return [
            'path' => $path,
            'size' => $size,
            'mime' => $mime,
            'checksum' => $checksum,
            'original_name' => $sanitizedName
        ];
    }

    public function moveToPermanent(string $tempPath, string $newFileName): string
    {
        $content = Storage::disk($this->disk)->get($tempPath);
        $newPath = 'materials/' . Str::uuid() . '/' . $newFileName;

        Storage::disk($this->disk)->put($newPath, $content);
        Storage::disk($this->disk)->delete($tempPath);

        return $newPath;
    }

    public function sanitizeFilename(string $filename): string
    {
        // Xavfli belgilarni tozalash
        $filename = str_replace(['../', '..\\', "\0"], '', $filename);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        return substr($filename, 0, 255);
    }

    public function generateSignedUrl(string $storagePath, int $expiryMinutes = null): string
    {
        $expiryMinutes = $expiryMinutes ?? config('materials.signed_url_expiry_minutes', 10);
        
        // Storage disk'dan direct URL
        $disk = Storage::disk($this->disk);
        
        if (method_exists($disk, 'temporaryUrl')) {
            return $disk->temporaryUrl($storagePath, now()->addMinutes($expiryMinutes));
        }

        // Fallback: Laravel URL
        return route('materials.download', ['path' => $storagePath]);
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
}
