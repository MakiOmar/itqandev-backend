<?php

namespace App\Jobs;

use App\Models\AppMedia as Media;
use App\Services\MediaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * WebP conversion and thumbnails after a media row is stored.
 */
class FinalizeUploadedMediaJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $mediaId)
    {
    }

    public function handle(MediaService $mediaService): void
    {
        $media = Media::query()->find($this->mediaId);
        if (! $media instanceof Media) {
            return;
        }

        $mediaService->finalizeUploadedMedia($media);
    }
}
