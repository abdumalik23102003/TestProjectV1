<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use finfo;

class UploadValidator
{
    public function validate(string $filePath, User $teacher, string $originalName): ?array
    {
        // File exists
        if (!file_exists($filePath)) {
            return ['error' => 'File not found'];
        }

        // Get mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        // Check extension
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = $this->getAllowedExtensions($mime);

        if (!in_array($ext, $allowed)) {
            return ['error' => 'File type not allowed: ' . $ext];
        }

        // Check file size
        $size = filesize($filePath);
        $maxSizeMb = config('materials.max_file_size_mb', 20);
        $maxSizeBytes = $maxSizeMb * 1024 * 1024;

        if ($size > $maxSizeBytes) {
            return ['error' => sprintf('File too large. Max: %d MB', $maxSizeMb)];
        }

        // Check teacher quota (simplified)
        $usedBytes = $teacher->materials()->sum('size_bytes');
        $quotaBytes = config('materials.teacher_storage_quota_gb', 5) * 1024 * 1024 * 1024;

        if ($usedBytes + $size > $quotaBytes) {
            return ['error' => 'Storage quota exceeded'];
        }

        // Check daily limit (simplified)
        $todayCount = $teacher->materials()
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($todayCount >= config('materials.max_uploads_per_day', 100)) {
            return ['error' => 'Daily upload limit exceeded'];
        }

        // Checksum
        $checksum = hash_file('sha256', $filePath);

        return [
            'mime' => $mime,
            'size' => $size,
            'checksum' => $checksum,
        ];
    }

    protected function getAllowedExtensions(string $mime): array
    {
        $mapping = [
            'application/pdf' => ['pdf'],
            'application/epub' => ['epub'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'text/plain' => ['txt'],
            'application/rtf' => ['rtf'],
            'application/vnd.ms-powerpoint' => ['ppt'],
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
            'audio/mpeg' => ['mp3'],
            'audio/mp4' => ['m4a'],
            'video/mp4' => ['mp4'],
            'video/quicktime' => ['mov'],
            'video/x-msvideo' => ['avi'],
            'video/x-matroska' => ['mkv'],
        ];

        return $mapping[$mime] ?? [];
    }
}
