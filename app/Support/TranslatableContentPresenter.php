<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\Project;
use Illuminate\Http\Request;

/**
 * Overlays translatable columns from translation rows when the requested UI locale
 * differs from the record's primary content locale (admin list / preview).
 */
final class TranslatableContentPresenter
{
    /**
     * Locale from X-Content-Locale header, validated against enabled site languages.
     */
    public static function requestedPresentationLocale(Request $request): ?string
    {
        $raw = $request->header('X-Content-Locale');
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $code = strtolower(trim($raw));
        foreach (SiteLanguages::all() as $row) {
            if ($row['code'] === $code) {
                return $code;
            }
        }

        return null;
    }

    public static function applyBlogPost(BlogPost $post, string $locale): void
    {
        $primary = SiteLanguages::primaryLocaleForContent($post->content_locale);
        if ($locale === $primary) {
            return;
        }

        if (! $post->relationLoaded('translations')) {
            $post->load('translations');
        }

        $row = $post->translations->firstWhere('locale', $locale);
        if ($row === null) {
            return;
        }

        if (is_string($row->title) && $row->title !== '') {
            $post->setAttribute('title', $row->title);
        }
        if (is_string($row->excerpt) && $row->excerpt !== '') {
            $post->setAttribute('excerpt', $row->excerpt);
        }
        if (is_string($row->content) && $row->content !== '') {
            $post->setAttribute('content', $row->content);
        }
    }

    public static function applyProject(Project $project, string $locale): void
    {
        $primary = SiteLanguages::primaryLocaleForContent($project->content_locale);
        if ($locale === $primary) {
            return;
        }

        if (! $project->relationLoaded('translations')) {
            $project->load('translations');
        }

        $row = $project->translations->firstWhere('locale', $locale);
        if ($row === null) {
            return;
        }

        if (is_string($row->title) && $row->title !== '') {
            $project->setAttribute('title', $row->title);
        }
        if (is_string($row->summary) && $row->summary !== '') {
            $project->setAttribute('summary', $row->summary);
        }
        if (is_string($row->description) && $row->description !== '') {
            $project->setAttribute('description', $row->description);
        }
    }
}
