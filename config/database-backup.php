<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database backup directory
    |--------------------------------------------------------------------------
    |
    | SQL dumps created from the admin Backup & Restore page are stored here.
    | Default lives under storage/app/private (already allowlisted by gitignore).
    |
    */
    'path' => env('DB_BACKUP_PATH', storage_path('app/private/backups')),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | After creating a backup, older files beyond this count are deleted (newest kept).
    | Set 0 to disable automatic pruning.
    |
    */
    'max_files' => max(0, (int) env('DB_BACKUP_MAX_FILES', 20)),

    /*
    |--------------------------------------------------------------------------
    | Optional CLI tools
    |--------------------------------------------------------------------------
    |
    | When set and executable, preferred over the PHP dump/restore fallback.
    | On XAMPP Windows, e.g. C:\xampp\mysql\bin\mysqldump.exe
    |
    */
    'mysqldump_path' => env('DB_BACKUP_MYSQLDUMP_PATH', ''),
    'mysql_cli_path' => env('DB_BACKUP_MYSQL_CLI_PATH', ''),
    'sqlite3_path' => env('DB_BACKUP_SQLITE3_PATH', ''),

    /*
    |--------------------------------------------------------------------------
    | Restore confirmation phrase
    |--------------------------------------------------------------------------
    |
    | Clients must send confirmation exactly equal to this string (case-sensitive).
    |
    */
    'confirm_phrase' => env('DB_BACKUP_CONFIRM_PHRASE', 'CONFIRM'),

    /*
    |--------------------------------------------------------------------------
    | Upload size (kilobytes) for restore-from-upload
    |--------------------------------------------------------------------------
    */
    'max_upload_kb' => max(1024, (int) env('DB_BACKUP_MAX_UPLOAD_KB', 512000)),

];
