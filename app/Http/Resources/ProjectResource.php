<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'description' => $this->description,
            'status' => $this->status,
            'link_url' => $this->link_url,
            'repo_url' => $this->repo_url,
            'demo_url' => $this->demo_url,
            'featured' => $this->featured,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'categories' => $this->whenLoaded('categories', function () {
                return $this->categories->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                ]);
            }) ?? [],
            'skills' => $this->whenLoaded('skills', function () {
                return $this->skills->map(fn ($skill) => [
                    'id' => $skill->id,
                    'name' => $skill->name,
                ]);
            }) ?? [],
            'testimonials' => $this->whenLoaded('testimonials'),
            'seoMeta' => $this->whenLoaded('seoMeta'),
            'media' => $this->getMediaData(),
        ];
    }

    /**
     * Get media data for the project.
     */
    protected function getMediaData(): array
    {
        $hero = $this->getFirstMedia('hero');
        $video = $this->getFirstMedia('video');
        
        return [
            'hero' => $hero ? $this->transformMediaItem($hero) : null,
            'video' => $video ? $this->transformMediaItem($video) : null,
        ];
    }

    /**
     * Transform a single media item for API response.
     */
    protected function transformMediaItem($media): array
    {
        $url = $media->getUrl();
        // Ensure URL is absolute
        if ($url && !filter_var($url, FILTER_VALIDATE_URL)) {
            $url = url($url);
        }
        
        return [
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
    }
}
