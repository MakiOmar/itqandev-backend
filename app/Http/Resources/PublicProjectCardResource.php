<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal project payload for public marketing (list / cards).
 */
class PublicProjectCardResource extends JsonResource
{
    protected static function decodeRichTextHtml(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        return html_entity_decode($html, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hero = $this->getFirstMedia('hero');
        $url = $hero ? $hero->getUrl() : null;
        if ($url && ! filter_var($url, FILTER_VALIDATE_URL)) {
            $url = url($url);
        }

        $tags = [];
        if ($this->relationLoaded('categories')) {
            foreach ($this->categories as $c) {
                $tags[] = $c->name;
            }
        }
        if ($this->relationLoaded('skills')) {
            foreach ($this->skills as $s) {
                $tags[] = $s->name;
            }
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'description' => self::decodeRichTextHtml($this->description),
            'featured' => (bool) $this->featured,
            'published_at' => $this->published_at?->toIso8601String(),
            'image' => $url,
            'image_alt' => $hero ? ($hero->name ?: $this->title) : null,
            'tags' => array_values(array_unique(array_filter($tags))),
        ];
    }
}
