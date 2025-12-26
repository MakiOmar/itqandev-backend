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
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use App\Models\AppMedia as Media;

class MediaService
{
    protected ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Process and store an uploaded file.
     */
    public function processUpload(UploadedFile $file, ?int $folderId = null, ?array $tags = null): Media
    {
        $mediaLibrary = \App\Models\MediaLibrary::instance();
        
        // Generate file path
        $path = $this->generateFilePath($file);
        
        // Add media to collection
        $media = $mediaLibrary
            ->addMedia($file)
            ->usingName($this->sanitizeFilename($file->getClientOriginalName()))
            ->toMediaCollection('default');

        // Update folder if provided
        if ($folderId) {
            $this->updateMediaFolder($media, $folderId);
        }

        // Add tags if provided
        if ($tags && count($tags) > 0) {
            $this->attachTags($media, $tags);
        }

        // Generate thumbnails for images
        if (config('media.generate_thumbnails') && $this->isImage($media)) {
            $this->generateThumbnails($media);
        }

        return $media;
    }

    /**
     * Generate file path based on configuration.
     */
    protected function generateFilePath(UploadedFile $file): string
    {
        $basePath = config('media.path', 'media');
        
        if (config('media.organize_by_date')) {
            $basePath .= '/' . date('Y') . '/' . date('m');
        }

        return $basePath;
    }

    /**
     * Sanitize filename.
     */
    protected function sanitizeFilename(string $filename): string
    {
        if (!config('media.sanitize_filenames')) {
            return $filename;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        
        // Remove special characters, keep only alphanumeric, spaces, hyphens, underscores
        $name = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $name);
        $name = Str::slug($name);
        
        return $name . '.' . $extension;
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
        $filePath = $media->getPath();

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
                $disk->put($thumbnailPath, (string) $resized->encode());
            }
        } catch (\Exception $e) {
            // Log error but don't fail the upload
            \Log::error('Failed to generate thumbnails: ' . $e->getMessage());
        }
    }

    /**
     * Get thumbnail path for a media item.
     */
    protected function getThumbnailPath(Media $media, string $sizeName): string
    {
        $path = dirname($media->getPath());
        $filename = pathinfo($media->file_name, PATHINFO_FILENAME);
        $extension = pathinfo($media->file_name, PATHINFO_EXTENSION);
        
        return $path . '/' . $filename . '-' . $sizeName . '.' . $extension;
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
                $filePath = $media->getPath();
                
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

