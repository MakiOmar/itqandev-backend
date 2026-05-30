<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Media Library Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the media library system including file size limits,
    | supported file types, storage settings, and image processing options.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Maximum File Size
    |--------------------------------------------------------------------------
    |
    | Maximum file size in bytes. Default is 100MB.
    |
    */
    'max_file_size' => (int) env('MEDIA_MAX_FILE_SIZE', 104857600), // 100MB

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The disk where media files will be stored.
    |
    */
    'disk' => env('MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Media Path
    |--------------------------------------------------------------------------
    |
    | Base path for media files within the storage disk.
    |
    */
    'path' => env('MEDIA_PATH', 'media'),

    /*
    |--------------------------------------------------------------------------
    | Generate Thumbnails
    |--------------------------------------------------------------------------
    |
    | Whether to automatically generate thumbnails for images.
    |
    */
    'generate_thumbnails' => env('MEDIA_GENERATE_THUMBNAILS', true),

    /*
    |--------------------------------------------------------------------------
    | Convert uploads to WebP
    |--------------------------------------------------------------------------
    |
    | When true, raster images (JPEG, PNG, AVIF, BMP) are re-encoded as WebP
    | after upload. EXIF and other metadata are stripped. SVG and GIF are kept
    | as-is. Operators can override via project settings (media_convert_to_webp).
    |
    */
    'convert_to_webp' => filter_var(env('MEDIA_CONVERT_TO_WEBP', true), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | WebP quality
    |--------------------------------------------------------------------------
    |
    | Encoder quality (1–100) when convert_to_webp is enabled.
    |
    */
    'webp_quality' => (int) env('MEDIA_WEBP_QUALITY', 85),

    /*
    |--------------------------------------------------------------------------
    | Organize by Date
    |--------------------------------------------------------------------------
    |
    | Whether to organize files by year/month/day (WordPress-style).
    | Files will be stored in: {path}/{year}/{month}/{day}/
    |
    */
    'organize_by_date' => env('MEDIA_ORGANIZE_BY_DATE', true),

    /*
    |--------------------------------------------------------------------------
    | Sanitize Filenames
    |--------------------------------------------------------------------------
    |
    | Whether to sanitize filenames (remove special characters, etc.).
    |
    */
    'sanitize_filenames' => env('MEDIA_SANITIZE_FILENAMES', true),

    /*
    |--------------------------------------------------------------------------
    | Unique Filenames
    |--------------------------------------------------------------------------
    |
    | Whether to ensure unique filenames (add timestamp if duplicate).
    |
    */
    'unique_filenames' => env('MEDIA_UNIQUE_FILENAMES', true),

    /*
    |--------------------------------------------------------------------------
    | Allowed File Types
    |--------------------------------------------------------------------------
    |
    | MIME types and extensions allowed for upload.
    |
    */
    'allowed_types' => [
        'image' => ['jpeg', 'jpg', 'png', 'gif', 'webp', 'avif', 'svg'],
        'video' => ['mp4', 'webm', 'ogg', 'avi', 'mov', 'wmv'],
        'audio' => ['mp3', 'wav', 'ogg', 'm4a', 'aac'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Sizes
    |--------------------------------------------------------------------------
    |
    | Image sizes to generate for uploaded images.
    |
    */
    'image_sizes' => [
        'thumbnail' => ['width' => 150, 'height' => 150, 'crop' => true],
        'medium' => ['width' => 300, 'height' => 300, 'crop' => false],
        'medium_large' => ['width' => 768, 'height' => null, 'crop' => false],
        'large' => ['width' => 1024, 'height' => 1024, 'crop' => false],
    ],

    /*
    |--------------------------------------------------------------------------
    | Max Files Per Upload
    |--------------------------------------------------------------------------
    |
    | Maximum number of files that can be uploaded in a single request.
    |
    */
    'max_files_per_upload' => env('MEDIA_MAX_FILES_PER_UPLOAD', 10),
];

