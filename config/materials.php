<?php

return [
    'max_file_size_mb' => (int) env('APP_MAX_FILE_SIZE_MB', 20),
    'teacher_storage_quota_gb' => (int) env('TEACHER_STORAGE_QUOTA_GB', 5),
    'max_uploads_per_day' => (int) env('MAX_UPLOADS_PER_DAY', 100),
    'signed_url_expiry_minutes' => (int) env('SIGNED_URL_EXPIRY_MINUTES', 10),
    'wizard_session_timeout' => (int) env('WIZARD_SESSION_TIMEOUT', 15),

    /*
    | Ruxsat etilgan fayl turlari: kengaytma => mumkin bo'lgan MIME turlari.
    | Bir xil fayl turini turli libmagic versiyalari turlicha aniqlashi mumkin,
    | shuning uchun bir nechta variant keltirilgan.
    */
    'allowed_types' => [
        'pdf' => ['application/pdf'],
        'epub' => ['application/epub+zip', 'application/zip', 'application/octet-stream'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream',
        ],
        'txt' => ['text/plain'],
        'rtf' => ['application/rtf', 'text/rtf'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office'],
        'pptx' => [
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/zip',
            'application/octet-stream',
        ],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'mp3' => ['audio/mpeg'],
        'm4a' => ['audio/mp4', 'audio/x-m4a', 'video/mp4'],
        'mp4' => ['video/mp4'],
        'mov' => ['video/quicktime'],
        'avi' => ['video/x-msvideo', 'video/avi'],
        'mkv' => ['video/x-matroska'],
    ],

    /*
    | ZIP asosidagi formatlar: MIME umumiy bo'lishi mumkin, shuning uchun
    | qo'shimcha ravishda fayl "PK" imzosi bilan boshlanishi tekshiriladi.
    */
    'zip_based_extensions' => ['epub', 'docx', 'pptx'],
];
