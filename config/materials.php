<?php

return [
    'max_file_size_mb' => env('APP_MAX_FILE_SIZE_MB', 20),
    'teacher_storage_quota_gb' => env('TEACHER_STORAGE_QUOTA_GB', 5),
    'max_uploads_per_day' => env('MAX_UPLOADS_PER_DAY', 100),
    'signed_url_expiry_minutes' => env('SIGNED_URL_EXPIRY_MINUTES', 10),
    'wizard_session_timeout' => env('WIZARD_SESSION_TIMEOUT', 15),
    
    'allowed_document_extensions' => ['pdf', 'epub', 'doc', 'docx', 'txt', 'rtf', 'ppt', 'pptx'],
    'allowed_image_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
    'allowed_audio_extensions' => ['mp3', 'm4a'],
    'allowed_video_extensions' => ['mp4', 'mov', 'avi', 'mkv'],
    
    'admin_user_id' => env('ADMIN_USER_ID', null),
];
