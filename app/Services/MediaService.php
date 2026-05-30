<?php

namespace App\Services;

use App\Models\MediaFolder;
use App\Models\MediaTag;
use App\Models\MediaUsage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use App\Models\AppMedia as Media;

class MediaService
{
    protected ImageManager $imageManager;

    public function __construct(
        protected MediaImageProcessor $mediaImageProcessor,
    ) {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * WebP conversion, thumbnails, and other post-upload image steps.
     */
    public function finalizeUploadedMedia(Media $media): Media
    {
        $media = $this->mediaImageProcessor->processAfterUpload($media);

        if (config('media.generate_thumbnails', true) && $this->isImage($media)) {
            $this->generateThumbnails($media);
        }

        return $media;
    }

    /**
     * Process and store an uploaded file.
     */
    public function processUpload(UploadedFile $file, ?int $folderId = null, ?array $tags = null): Media
    {
        try {
            // Verify MIME type matches file extension
            $this->verifyMimeType($file);
        } catch (\Exception $mimeError) {
            throw $mimeError;
        }
        
        $mediaLibrary = \App\Models\MediaLibrary::instance();
        
        try {
            // Add media to collection
            // The path will be automatically organized by date using the DatePathGenerator
            $media = $mediaLibrary
                ->addMedia($file)
                ->usingName($this->sanitizeFilename($file->getClientOriginalName()))
                ->toMediaCollection('default');
        } catch (\Exception $addMediaError) {
            throw $addMediaError;
        }

        // Update folder if provided
        if ($folderId) {
            $this->updateMediaFolder($media, $folderId);
        }

        // Add tags if provided
        if ($tags && count($tags) > 0) {
            $this->attachTags($media, $tags);
        }

        return $this->finalizeUploadedMedia($media);
    }

    /**
     * Sanitize filename while preserving Unicode characters (including Arabic).
     */
    protected function sanitizeFilename(string $filename): string
    {
        if (!config('media.sanitize_filenames', true)) {
            return $filename;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        
        // Remove only problematic characters, preserve Unicode (Arabic, etc.)
        // Keep alphanumeric, spaces, hyphens, underscores, and Unicode characters
        $name = preg_replace('/[<>:"|?*\\x00-\\x1F\\x7F]/u', '', $name);
        
        // Remove path traversal attempts
        $name = str_replace(['../', '..\\', '/', '\\'], '', $name);
        
        // Trim whitespace
        $name = trim($name);
        
        // If name is empty after sanitization, use a default
        if (empty($name)) {
            $name = 'file_' . time();
        }
        
        // Sanitize extension - only allow alphanumeric and common safe characters
        $extension = preg_replace('/[^a-zA-Z0-9]/', '', $extension);
        
        return $name . '.' . $extension;
    }

    /**
     * Verify MIME type matches file extension and content.
     */
    protected function verifyMimeType(UploadedFile $file): void
    {
        $allowedMimes = [
            // Images
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/avif',
            // Videos
            'video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo',
            // Documents
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            // Audio
            'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/m4a', 'audio/aac',
            // Text
            'text/plain', 'text/csv',
        ];

        $detectedMime = $file->getMimeType();
        $clientMime = $file->getClientMimeType();
        
        // Verify detected MIME type is in allowed list
        if (!in_array($detectedMime, $allowedMimes, true)) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], [])->errors()->add('file', 'File type not allowed: ' . $detectedMime)
            );
        }

        // Additional verification: check file content using finfo if available
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $contentMime = finfo_file($finfo, $file->getRealPath());
            finfo_close($finfo);

            // Verify content MIME matches detected MIME
            if ($contentMime !== $detectedMime && !$this->isMimeCompatible($contentMime, $detectedMime)) {
                throw new \Illuminate\Validation\ValidationException(
                    validator([], [])->errors()->add('file', 'File content does not match declared type')
                );
            }
        }
    }

    /**
     * Check if two MIME types are compatible (e.g., jpg vs jpeg).
     */
    protected function isMimeCompatible(string $mime1, string $mime2): bool
    {
        // Normalize MIME types
        $mime1 = strtolower($mime1);
        $mime2 = strtolower($mime2);

        // Exact match
        if ($mime1 === $mime2) {
            return true;
        }

        // JPEG variations
        if (in_array($mime1, ['image/jpeg', 'image/jpg']) && in_array($mime2, ['image/jpeg', 'image/jpg'])) {
            return true;
        }

        return false;
    }

    /**
     * Check if media is an image.
     */
    protected function isImage(Media $media): bool
    {
        return str_starts_with($media->mime_type, 'image/');
    }

    /**
     * Generate thumbnails for an image.
     */
    protected function generateThumbnails(Media $media): void
    {
        if (!$media->disk) {
            return; // Can't generate thumbnails without a disk
        }
        
        $sizes = config('media.image_sizes', []);
        $disk = Storage::disk($media->disk);
        $filePath = $media->getPathRelativeToRoot();

        if (!$disk->exists($filePath)) {
            return;
        }

        try {
            $image = $this->imageManager->read($disk->get($filePath));

            foreach ($sizes as $sizeName => $size) {
                $width = $size['width'] ?? null;
                $height = $size['height'] ?? null;
                $crop = $size['crop'] ?? false;

                $resized = clone $image;

                if ($crop && $width && $height) {
                    $resized->cover($width, $height);
                } else {
                    $resized->scale($width, $height);
                }

                $thumbnailPath = $this->getThumbnailPath($media, $sizeName);
                $disk->put($thumbnailPath, $this->encodeImageVariant($resized, $media));
            }
        } catch (\Exception $e) {
            // Log error but don't fail the upload
            \Log::error('Failed to generate thumbnails: ' . $e->getMessage());
        }
    }

    /**
     * Encode a resized variant; match parent format and strip metadata for WebP.
     */
    protected function encodeImageVariant($image, Media $media): string
    {
        if (strtolower((string) $media->mime_type) === 'image/webp') {
            $quality = max(1, min(100, (int) config('media.webp_quality', 85)));

            return (string) $image->encode(new WebpEncoder(quality: $quality, strip: true));
        }

        return (string) $image->encode();
    }

    /**
     * Get thumbnail path for a media item.
     */
    protected function getThumbnailPath(Media $media, string $sizeName): string
    {
        $path = dirname($media->getPathRelativeToRoot());
        $filename = pathinfo($media->file_name, PATHINFO_FILENAME);
        $extension = pathinfo($media->file_name, PATHINFO_EXTENSION);

        return $path.'/'.$filename.'-'.$sizeName.'.'.$extension;
    }

    /**
     * Update media folder.
     */
    public function updateMediaFolder(Media $media, ?int $folderId): void
    {
        if ($folderId) {
            MediaFolder::findOrFail($folderId);
        }
        
        $media->update(['folder_id' => $folderId]);
    }

    /**
     * Attach tags to media.
     */
    public function attachTags(Media $media, array $tagNames): void
    {
        $tagIds = [];
        
        foreach ($tagNames as $tagName) {
            $tag = MediaTag::firstOrCreate(
                ['slug' => Str::slug($tagName)],
                ['name' => $tagName]
            );
            $tagIds[] = $tag->id;
        }

        // Sync tags via pivot table
        DB::table('media_media_tag')
            ->where('media_id', $media->id)
            ->delete();
        
        foreach ($tagIds as $tagId) {
            DB::table('media_media_tag')->insert([
                'media_id' => $media->id,
                'media_tag_id' => $tagId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Track media usage.
     */
    public function trackUsage(Media $media, $usable, ?string $collectionName = null): void
    {
        MediaUsage::updateOrCreate(
            [
                'media_id' => $media->id,
                'usable_type' => get_class($usable),
                'usable_id' => $usable->id,
                'collection_name' => $collectionName,
            ]
        );
    }

    /**
     * Delete media and all related files.
     */
    public function deleteMedia(Media $media): void
    {
        $mediaId = $media->id;
        $diskName = $media->disk ?: config('media-library.disk_name', 'public');
        
        // Delete thumbnails first (if we generated any custom ones)
        if ($this->isImage($media) && $media->disk) {
            try {
                $sizes = config('media.image_sizes', []);
                $disk = Storage::disk($diskName);
                
                foreach (array_keys($sizes) as $sizeName) {
                    try {
                        $thumbnailPath = $this->getThumbnailPath($media, $sizeName);
                        if ($thumbnailPath && $disk->exists($thumbnailPath)) {
                            $disk->delete($thumbnailPath);
                        }
                    } catch (\Exception $e) {
                        // Thumbnail might not exist or already deleted - ignore
                    }
                }
            } catch (\Exception $e) {
                // Thumbnails might not exist - ignore
            }
        }

        // Delete usage records
        MediaUsage::where('media_id', $mediaId)->delete();

        // Delete tag relationships
        DB::table('media_media_tag')->where('media_id', $mediaId)->delete();

        // Try to delete the main file before deleting the model
        // Spatie's observer will also try to delete it, so this ensures cleanup even if observer fails
        if ($media->disk) {
            try {
                $disk = Storage::disk($diskName);
                $filePath = $media->getPathRelativeToRoot();

                if ($disk->exists($filePath)) {
                    $disk->delete($filePath);
                }
            } catch (\Exception $e) {
                // File might already be deleted or path might be invalid
                // This is not critical as Spatie's observer will handle cleanup
            }
        }

        // Delete the media record from database
        // Spatie's MediaObserver will also attempt to delete the file
        $media->delete();
    }
}

