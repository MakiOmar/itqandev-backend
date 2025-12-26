<?php

namespace App\Support\MediaLibrary;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

class DatePathGenerator extends DefaultPathGenerator
{
    /**
     * Get the path for the given media, relative to the root storage path.
     */
    public function getPath(Media $media): string
    {
        // Only apply date-based organization if enabled
        if (!config('media.organize_by_date', true)) {
            return parent::getPath($media);
        }

        $basePath = config('media.path', 'media');
        $createdAt = $media->created_at ?? now();
        
        return sprintf(
            '%s/%s/%s/%s',
            $basePath,
            $createdAt->format('Y'),
            $createdAt->format('m'),
            $createdAt->format('d')
        );
    }

    /**
     * Get the path for conversions of the given media, relative to the root storage path.
     */
    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media) . '/conversions';
    }

    /**
     * Get the path for responsive images of the given media, relative to the root storage path.
     */
    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media) . '/responsive-images';
    }
}

