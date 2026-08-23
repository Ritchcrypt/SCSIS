<?php

return [
    'enabled' => filter_var(
        env('BACKUP_ENABLED', false),
        FILTER_VALIDATE_BOOL
    ),

    'schedule_time' => env('BACKUP_SCHEDULE_TIME', '03:15'),
    'retention_count' => max(
        1,
        (int) env('BACKUP_RETENTION_COUNT', 14)
    ),

    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),

    'google_drive' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
        'root_folder_name' => env(
            'GOOGLE_DRIVE_BACKUP_ROOT_FOLDER',
            'TabangNow Backups'
        ),
        'database_folder_name' => 'database',
        'uploads_folder_name' => 'uploads',
    ],
];
