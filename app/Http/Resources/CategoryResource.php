<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_featured' => $this->is_featured,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'projects_count' => $this->when(isset($this->projects_count), $this->projects_count),
            'projects' => $this->whenLoaded('projects', function () {
                return $this->projects->map(fn ($project) => [
                    'id' => $project->id,
                    'title' => $project->title,
                ]);
            }),
            'seoMeta' => $this->whenLoaded('seoMeta'),
            'media' => $this->when($this->relationLoaded('media'), function () {
                return $this->media->map(fn ($media) => [
                    'id' => $media->id,
                    'collection_name' => $media->collection_name,
                    'url' => $media->getUrl(),
                    'name' => $media->name,
                ]);
            }),
        ];
    }
}
