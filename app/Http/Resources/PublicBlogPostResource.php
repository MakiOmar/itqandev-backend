<?php

namespace App\Http\Resources;

use App\Support\SeoMetaPresenter;
use App\Support\SiteLanguages;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public blog post payload for marketing site.
 */
class PublicBlogPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $featured = $this->getFirstMedia('featured_image');
        $coverUrl = $featured ? $featured->getUrl() : null;
        if ($coverUrl && ! filter_var($coverUrl, FILTER_VALIDATE_URL)) {
            $coverUrl = url($coverUrl);
        }

        $present = $request->header('X-Content-Locale');
        $present = is_string($present) ? strtolower(trim($present)) : null;
        $primaryLocale = SiteLanguages::primaryLocaleForContent($this->content_locale);

        $seoMeta = null;
        if ($this->relationLoaded('seoMetas')) {
            $picked = SeoMetaPresenter::pickLocalized($this->seoMetas, $present, $primaryLocale);
            $seoMeta = SeoMetaPresenter::toPublicSnippet($picked);
        }

        $authorName = $this->relationLoaded('author') && $this->author
            ? $this->author->name
            : null;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->content,
            'content' => $this->content,
            'date' => $this->published_at?->toIso8601String() ?? $this->created_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'author' => $authorName,
            'coverImage' => $coverUrl,
            'cover_image' => $coverUrl,
            'seo_meta' => $seoMeta,
            'seoMeta' => $seoMeta,
        ];
    }
}
