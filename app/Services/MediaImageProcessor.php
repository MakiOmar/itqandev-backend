<?php

namespace App\Services;

use App\Models\AppMedia as Media;
use App\Support\MediaSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class MediaImageProcessor
{
    /** @var list<string> */
    private const CONVERTIBLE_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/avif',
        'image/bmp',
        'image/x-ms-bmp',
    ];

    protected ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Convert raster uploads to WebP (when enabled) and strip EXIF/metadata.
     */
    public function processAfterUpload(Media $media): Media
    {
        if (! MediaSettings::convertToWebpEnabled()) {
            return $media;
        }

        if (! $this->shouldConvertToWebp($media)) {
            return $media;
        }

        return $this->convertMediaFileToWebp($media);
    }

    protected function shouldConvertToWebp(Media $media): bool
    {
        $mime = strtolower((string) $media->mime_type);

        if ($mime === '' || ! str_starts_with($mime, 'image/')) {
            return false;
        }

        if (in_array($mime, ['image/webp', 'image/svg+xml', 'image/gif'], true)) {
            return false;
        }

        return in_array($mime, self::CONVERTIBLE_MIME_TYPES, true);
    }

    protected function convertMediaFileToWebp(Media $media): Media
    {
        if (! $media->disk) {
            return $media;
        }

        $disk = Storage::disk($media->disk);
        $sourcePath = $media->getPathRelativeToRoot();

        if (! $disk->exists($sourcePath)) {
            return $media;
        }

        try {
            $image = $this->imageManager->read($disk->get($sourcePath));
            $quality = max(1, min(100, (int) config('media.webp_quality', 85)));
            $encoded = $image->encode(new WebpEncoder(quality: $quality, strip: true));

            $newFileName = pathinfo($media->file_name, PATHINFO_FILENAME).'.webp';
            $targetPath = dirname($sourcePath).'/'.$newFileName;

            $disk->put($targetPath, (string) $encoded);

            if ($targetPath !== $sourcePath) {
                $disk->delete($sourcePath);
            }

            $media->file_name = $newFileName;
            $media->mime_type = 'image/webp';
            $media->size = strlen((string) $encoded);
            $media->save();

            return $media->fresh() ?? $media;
        } catch (\Throwable $e) {
            Log::warning('Media WebP conversion failed', [
                'media_id' => $media->id,
                'message' => $e->getMessage(),
            ]);

            return $media;
        }
    }
}
