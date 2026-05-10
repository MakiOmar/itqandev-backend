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
            'content_locale' => $this->content_locale,
            'summary' => $this->summary,
            'description' => self::decodeRichTextHtml($this->description),
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
            'seoMetas' => $this->whenLoaded('seoMetas', function () {
                return $this->seoMetas->map(fn ($m) => [
                    'id' => $m->id,
                    'locale' => $m->locale,
                    'meta_title' => $m->meta_title,
                    'meta_description' => $m->meta_description,
                    'canonical_url' => $m->canonical_url,
                    'og_title' => $m->og_title,
                    'og_description' => $m->og_description,
                    'og_image' => $m->og_image,
                    'twitter_card' => $m->twitter_card,
                    'schema' => $m->schema,
                ])->values();
            }),
            'translations' => $this->whenLoaded('translations', function () {
                return $this->translations->map(fn ($t) => [
                    'locale' => $t->locale,
                    'title' => $t->title,
                    'summary' => $t->summary,
                    'description' => self::decodeRichTextHtml($t->description),
                ])->values();
            }),
            'media' => $this->getMediaData(),
        ];
    }

    /**
     * Undo numeric HTML entities from legacy DOM saveHTML storage so admin/API get UTF-8.
     */
    protected static function decodeRichTextHtml(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        return html_entity_decode($html, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
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
        if ($url && ! filter_var($url, FILTER_VALIDATE_URL)) {
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
