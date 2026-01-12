<?php

namespace App\Services;

use App\Models\AppMedia as Media;

class MediaTransformerService
{
    /**
     * Transform a media item for API response.
     */
    public function transform(Media $media, bool $detailed = false): array
    {
        $url = $media->getUrl();
        // Ensure URL is absolute
        if ($url && !filter_var($url, FILTER_VALIDATE_URL)) {
            $url = url($url);
        }

        $data = [
            'id' => $media->id,
            'file_name' => $media->file_name,
            'name' => $media->name,
            'collection' => $media->collection_name,
            'collection_name' => $media->collection_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'url' => $url,
            'model_type' => $media->model_type,
            'model_id' => $media->model_id,
            'created_at' => $media->created_at?->toIso8601String(),
            'alt_text' => $media->getCustomProperty('alt_text'),
        ];

        if ($media->relationLoaded('folder') && $media->folder) {
            $data['folder'] = [
                'id' => $media->folder->id,
                'name' => $media->folder->name,
                'slug' => $media->folder->slug ?? null,
            ];
        }

        if ($media->relationLoaded('tags')) {
            $data['tags'] = $media->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug ?? null,
                ];
            });
        }

        if ($detailed && $media->relationLoaded('usages')) {
            $data['usages'] = $media->usages->map(function ($usage) {
                return [
                    'id' => $usage->id,
                    'usable_type' => $usage->usable_type,
                    'usable_id' => $usage->usable_id,
                    'collection_name' => $usage->collection_name,
                ];
            });
        }

        return $data;
    }

    /**
     * Transform multiple media items.
     */
    public function transformCollection($mediaCollection, bool $detailed = false): array
    {
        return $mediaCollection->map(function ($media) use ($detailed) {
            return $this->transform($media, $detailed);
        })->toArray();
    }
}
