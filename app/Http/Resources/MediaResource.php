<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $url = $this->getUrl();
        // Ensure URL is absolute
        if ($url && !filter_var($url, FILTER_VALIDATE_URL)) {
            $url = url($url);
        }

        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'name' => $this->name,
            'collection' => $this->collection_name,
            'collection_name' => $this->collection_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'url' => $url,
            'model_type' => $this->model_type,
            'model_id' => $this->model_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'alt_text' => $this->getCustomProperty('alt_text'),
            'description' => $this->getCustomProperty('description'),
            'folder' => $this->whenLoaded('folder', function () {
                return [
                    'id' => $this->folder->id,
                    'name' => $this->folder->name,
                ];
            }),
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                ]);
            }),
        ];
    }
}
