<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Page;
use App\Models\Project;
use App\Models\Service;
use App\Models\Skill;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Overlays translatable columns from translation rows when the requested UI locale
 * differs from the record's primary content locale (admin list / preview).
 */
final class TranslatableContentPresenter
{
    public static function hasProjectContentForLocale(Project $project, string $locale): bool
    {
        return self::hasTranslatedContentForLocale(
            SiteLanguages::primaryLocaleForContent($project->content_locale),
            $project,
            $locale,
            ['title', 'summary', 'description']
        );
    }

    public static function hasCategoryContentForLocale(Category $category, string $locale): bool
    {
        return self::hasTranslatedContentForLocale(
            SiteLanguages::primaryLocaleForContent($category->content_locale),
            $category,
            $locale,
            ['name', 'description']
        );
    }

    public static function hasTestimonialContentForLocale(Testimonial $testimonial, string $locale): bool
    {
        return self::hasTranslatedContentForLocale(
            SiteLanguages::primaryLocaleForContent($testimonial->content_locale),
            $testimonial,
            $locale,
            ['content', 'client_role', 'company']
        );
    }

    public static function hasServiceContentForLocale(Service $service, string $locale): bool
    {
        return self::hasTranslatedContentForLocale(
            SiteLanguages::primaryLocaleForContent($service->content_locale),
            $service,
            $locale,
            ['name', 'short_description', 'description', 'process', 'deliverables']
        );
    }

    public static function hasPageContentForLocale(Page $page, string $locale): bool
    {
        return self::hasTranslatedContentForLocale(
            SiteLanguages::primaryLocaleForContent($page->content_locale),
            $page,
            $locale,
            ['title', 'excerpt']
        );
    }

    public static function hasFormContentForLocale(\App\Models\Form $form, string $locale): bool
    {
        return self::hasTranslatedContentForLocale(
            SiteLanguages::primaryLocaleForContent($form->content_locale),
            $form,
            $locale,
            ['title']
        );
    }

    public static function hasBlogPostContentForLocale(BlogPost $post, string $locale): bool
    {
        return self::hasTranslatedContentForLocale(
            SiteLanguages::primaryLocaleForContent($post->content_locale),
            $post,
            $locale,
            ['title', 'excerpt', 'content']
        );
    }

    public static function hasSkillContentForLocale(Skill $skill, string $locale): bool
    {
        return self::hasTranslatedContentForLocale(
            SiteLanguages::primaryLocaleForContent($skill->content_locale),
            $skill,
            $locale,
            ['name', 'description']
        );
    }

    /**
     * Restrict a listing query to rows with content for the presentation locale.
     */
    public static function scopeQueryForPresentationLocale(Builder $query, ?string $present): void
    {
        if ($present === null || $present === '') {
            return;
        }

        $siteDefaultLocale = SiteLanguages::defaultCode();

        $query->where(function ($q) use ($present, $siteDefaultLocale) {
            $q->where('content_locale', $present);
            if ($present === $siteDefaultLocale) {
                $q->orWhereNull('content_locale');
            }
            $q->orWhereHas('translations', function ($tq) use ($present) {
                $tq->where('locale', $present);
            });
        });
    }

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

    public static function applyCategory(Category $category, string $locale): void
    {
        $primary = SiteLanguages::primaryLocaleForContent($category->content_locale);
        if ($locale === $primary) {
            return;
        }

        if (! $category->relationLoaded('translations')) {
            $category->load('translations');
        }

        $row = $category->translations->firstWhere('locale', $locale);
        if ($row === null) {
            return;
        }

        if (is_string($row->name) && $row->name !== '') {
            $category->setAttribute('name', $row->name);
        }
        if (is_string($row->description) && $row->description !== '') {
            $category->setAttribute('description', $row->description);
        }
    }

    public static function applyTestimonial(Testimonial $testimonial, string $locale): void
    {
        $primary = SiteLanguages::primaryLocaleForContent($testimonial->content_locale);
        if ($locale === $primary) {
            return;
        }

        if (! $testimonial->relationLoaded('translations')) {
            $testimonial->load('translations');
        }

        $row = $testimonial->translations->firstWhere('locale', $locale);
        if ($row === null) {
            return;
        }

        if (is_string($row->content) && $row->content !== '') {
            $testimonial->setAttribute('content', $row->content);
        }
        if (is_string($row->client_role) && $row->client_role !== '') {
            $testimonial->setAttribute('client_role', $row->client_role);
        }
        if (is_string($row->company) && $row->company !== '') {
            $testimonial->setAttribute('company', $row->company);
        }
    }

    public static function applySkill(Skill $skill, string $locale): void
    {
        $primary = SiteLanguages::primaryLocaleForContent($skill->content_locale);
        if ($locale === $primary) {
            return;
        }

        if (! $skill->relationLoaded('translations')) {
            $skill->load('translations');
        }

        $row = $skill->translations->firstWhere('locale', $locale);
        if ($row === null) {
            return;
        }

        if (is_string($row->name) && $row->name !== '') {
            $skill->setAttribute('name', $row->name);
        }
        if (is_string($row->description) && $row->description !== '') {
            $skill->setAttribute('description', $row->description);
        }
    }

    public static function applyService(Service $service, string $locale): void
    {
        $primary = SiteLanguages::primaryLocaleForContent($service->content_locale);
        if ($locale === $primary) {
            return;
        }

        if (! $service->relationLoaded('translations')) {
            $service->load('translations');
        }

        $row = $service->translations->first(
            static fn ($t) => strtolower((string) $t->locale) === $locale
        );
        if ($row === null) {
            return;
        }

        if (is_string($row->name) && $row->name !== '') {
            $service->setAttribute('name', $row->name);
        }
        if (is_string($row->short_description) && $row->short_description !== '') {
            $service->setAttribute('short_description', $row->short_description);
        }
        if (is_string($row->description) && $row->description !== '') {
            $service->setAttribute('description', $row->description);
        }
        if (is_array($row->process) && count($row->process) > 0) {
            $service->setAttribute('process', $row->process);
        }
        if (is_array($row->deliverables) && count($row->deliverables) > 0) {
            $service->setAttribute('deliverables', $row->deliverables);
        }
    }

    public static function applyPage(Page $page, string $locale): void
    {
        $primary = SiteLanguages::primaryLocaleForContent($page->content_locale);
        if ($locale === $primary) {
            return;
        }

        if (! $page->relationLoaded('translations')) {
            $page->load('translations');
        }

        $row = $page->translations->first(
            static fn ($t) => strtolower((string) $t->locale) === $locale
        );
        if ($row === null) {
            return;
        }

        if (is_string($row->title) && $row->title !== '') {
            $page->setAttribute('title', $row->title);
        }
        if (is_string($row->excerpt) && $row->excerpt !== '') {
            $page->setAttribute('excerpt', $row->excerpt);
        }
    }

    public static function applyForm(\App\Models\Form $form, string $locale): void
    {
        $primary = SiteLanguages::primaryLocaleForContent($form->content_locale);
        if ($locale === $primary) {
            return;
        }

        if (! $form->relationLoaded('translations')) {
            $form->load('translations');
        }

        $row = $form->translations->first(
            static fn ($t) => strtolower((string) $t->locale) === $locale
        );
        if ($row === null) {
            return;
        }

        if (is_string($row->title) && $row->title !== '') {
            $form->setAttribute('title', $row->title);
        }
    }

    /**
     * @param  array<int, string>  $fields
     */
    private static function hasTranslatedContentForLocale(string $primary, mixed $model, string $locale, array $fields): bool
    {
        if ($locale === $primary) {
            return true;
        }

        if (! $model->relationLoaded('translations')) {
            $model->load('translations');
        }

        $row = $model->translations->first(static fn ($t) => strtolower((string) $t->locale) === $locale);
        if ($row === null) {
            return false;
        }

        foreach ($fields as $field) {
            $value = $row->{$field} ?? null;
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
            if (is_array($value) && count($value) > 0) {
                return true;
            }
        }

        return false;
    }
}
