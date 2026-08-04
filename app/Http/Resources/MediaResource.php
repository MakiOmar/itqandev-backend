<?php

namespace App\Http\Resources;

use App\Support\Media\LocalizedMediaMeta;
use App\Support\SiteLanguages;
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
        if ($url && ! filter_var($url, FILTER_VALIDATE_URL)) {
            $url = url($url);
        }

        $defaultLocale = SiteLanguages::defaultCode();
        $localeParam = $request->query('locale') ?? $request->header('X-Content-Locale');
        $locale = is_string($localeParam) && trim($localeParam) !== ''
            ? strtolower(trim($localeParam))
            : $defaultLocale;

        $meta = LocalizedMediaMeta::resolve($this->resource, $locale, $defaultLocale);

        $payload = [
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
            'alt_text' => $meta['alt_text'],
            'description' => $meta['description'],
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

        // Admin edit forms need the full translation map when translations are loaded.
        if ($this->relationLoaded('translations')) {
            $payload['translations'] = LocalizedMediaMeta::translationsBag($this->resource);
        }

        return $payload;
    }
}
