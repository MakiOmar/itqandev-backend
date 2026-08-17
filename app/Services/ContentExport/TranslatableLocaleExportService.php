<?php

namespace App\Services\ContentExport;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Page;
use App\Models\Project;
use App\Models\Service;
use App\Models\Skill;
use App\Models\Testimonial;
use App\Support\PageHierarchy;
use App\Support\ContentExportEnvelope;
use App\Support\TranslatableContentPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class TranslatableLocaleExportService
{
    /**
     * @param  array<int, int>|null  $ids
     * @return array<string, mixed>
     */
    public function buildEnvelope(string $entity, string $locale, ?array $ids = null): array
    {
        ContentExportEnvelope::assertEntity($entity);
        $items = $this->recordsForLocale($entity, $locale, $ids)
            ->map(fn (Model $model) => $this->mapExportItem($entity, $model))
            ->values()
            ->all();

        return ContentExportEnvelope::build($entity, $locale, $items);
    }

    /**
     * @param  array<int, int>|null  $ids
     * @return Collection<int, Model>
     */
    public function recordsForLocale(string $entity, string $locale, ?array $ids = null): Collection
    {
        $query = $this->baseQuery($entity);
        TranslatableContentPresenter::scopeQueryForPresentationLocale($query, $locale);

        if ($ids !== null && $ids !== []) {
            $query->whereIn('id', $ids);
        }

        $records = $query->get();

        $filtered = $records
            ->map(function (Model $model) use ($entity, $locale) {
                $this->applyPresentation($entity, $model, $locale);

                return $model;
            })
            ->filter(fn (Model $model) => $this->hasContentForLocale($entity, $model, $locale))
            ->values();

        if ($entity === ContentExportEnvelope::ENTITY_PAGES) {
            return collect(PageHierarchy::flattenForAdmin($filtered));
        }

        return $filtered;
    }

    private function baseQuery(string $entity): Builder
    {
        return match ($entity) {
            ContentExportEnvelope::ENTITY_CATEGORIES => Category::query()
                ->with(['translations'])
                ->orderBy('name'),
            ContentExportEnvelope::ENTITY_SKILLS => Skill::query()
                ->with(['translations'])
                ->orderBy('name'),
            ContentExportEnvelope::ENTITY_PROJECTS => Project::query()
                ->with(['translations'])
                ->orderBy('title'),
            ContentExportEnvelope::ENTITY_SERVICES => Service::query()
                ->with(['translations'])
                ->orderBy('sort_order')
                ->orderBy('id'),
            ContentExportEnvelope::ENTITY_BLOG_POSTS => BlogPost::query()
                ->with(['translations'])
                ->orderBy('title'),
            ContentExportEnvelope::ENTITY_TESTIMONIALS => Testimonial::query()
                ->with(['translations'])
                ->orderBy('id'),
            ContentExportEnvelope::ENTITY_PAGES => Page::query()
                ->with(['translations', 'parent:id,slug'])
                ->orderBy('id'),
            default => throw new \InvalidArgumentException('Unknown entity: '.$entity),
        };
    }

    private function applyPresentation(string $entity, Model $model, string $locale): void
    {
        match ($entity) {
            ContentExportEnvelope::ENTITY_CATEGORIES => TranslatableContentPresenter::applyCategory($model, $locale),
            ContentExportEnvelope::ENTITY_SKILLS => TranslatableContentPresenter::applySkill($model, $locale),
            ContentExportEnvelope::ENTITY_PROJECTS => TranslatableContentPresenter::applyProject($model, $locale),
            ContentExportEnvelope::ENTITY_SERVICES => TranslatableContentPresenter::applyService($model, $locale),
            ContentExportEnvelope::ENTITY_BLOG_POSTS => TranslatableContentPresenter::applyBlogPost($model, $locale),
            ContentExportEnvelope::ENTITY_TESTIMONIALS => TranslatableContentPresenter::applyTestimonial($model, $locale),
            ContentExportEnvelope::ENTITY_PAGES => TranslatableContentPresenter::applyPage($model, $locale),
            default => null,
        };
    }

    private function hasContentForLocale(string $entity, Model $model, string $locale): bool
    {
        return match ($entity) {
            ContentExportEnvelope::ENTITY_CATEGORIES => TranslatableContentPresenter::hasCategoryContentForLocale($model, $locale),
            ContentExportEnvelope::ENTITY_SKILLS => TranslatableContentPresenter::hasSkillContentForLocale($model, $locale),
            ContentExportEnvelope::ENTITY_PROJECTS => TranslatableContentPresenter::hasProjectContentForLocale($model, $locale),
            ContentExportEnvelope::ENTITY_SERVICES => TranslatableContentPresenter::hasServiceContentForLocale($model, $locale),
            ContentExportEnvelope::ENTITY_BLOG_POSTS => TranslatableContentPresenter::hasBlogPostContentForLocale($model, $locale),
            ContentExportEnvelope::ENTITY_TESTIMONIALS => TranslatableContentPresenter::hasTestimonialContentForLocale($model, $locale),
            ContentExportEnvelope::ENTITY_PAGES => TranslatableContentPresenter::hasPageContentForLocale($model, $locale),
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function mapExportItem(string $entity, Model $model): array
    {
        return match ($entity) {
            ContentExportEnvelope::ENTITY_CATEGORIES => [
                'id' => $model->id,
                'slug' => $model->slug,
                'name' => $model->name,
                'description' => $model->description,
                'is_featured' => (bool) $model->is_featured,
            ],
            ContentExportEnvelope::ENTITY_SKILLS => [
                'id' => $model->id,
                'slug' => $model->slug,
                'name' => $model->name,
                'description' => $model->description,
                'icon_hint' => $model->icon_hint,
            ],
            ContentExportEnvelope::ENTITY_PROJECTS => [
                'id' => $model->id,
                'slug' => $model->slug,
                'title' => $model->title,
                'summary' => $model->summary,
                'description' => $model->description,
                'featured' => (bool) $model->featured,
            ],
            ContentExportEnvelope::ENTITY_SERVICES => [
                'id' => $model->id,
                'slug' => $model->slug,
                'name' => $model->name,
                'short_description' => $model->short_description,
                'description' => $model->description,
                'process' => $model->process,
                'deliverables' => $model->deliverables,
            ],
            ContentExportEnvelope::ENTITY_BLOG_POSTS => [
                'id' => $model->id,
                'slug' => $model->slug,
                'title' => $model->title,
                'excerpt' => $model->excerpt,
                'content' => $model->content,
                'featured' => (bool) $model->featured,
            ],
            ContentExportEnvelope::ENTITY_TESTIMONIALS => [
                'id' => $model->id,
                'project_id' => $model->project_id,
                'client_name' => $model->client_name,
                'content' => $model->content,
                'client_role' => $model->client_role,
                'company' => $model->company,
            ],
            ContentExportEnvelope::ENTITY_PAGES => [
                'id' => $model->id,
                'slug' => $model->slug,
                'title' => $model->title,
                'excerpt' => $model->excerpt,
                'status' => $model->status,
                'parent_id' => $model->parent_id,
                'parent_slug' => $model->relationLoaded('parent')
                    ? ($model->parent?->slug)
                    : ($model->parent_id
                        ? Page::query()->whereKey((int) $model->parent_id)->value('slug')
                        : null),
                'exclude_from_search' => (bool) $model->exclude_from_search,
                'sections' => is_array($model->sections) ? $model->sections : [],
            ],
            default => throw new \InvalidArgumentException('Unknown entity: '.$entity),
        };
    }

    public static function listCachePrefix(string $entity): ?string
    {
        return match ($entity) {
            ContentExportEnvelope::ENTITY_CATEGORIES => 'categories:list:v3:json',
            ContentExportEnvelope::ENTITY_SKILLS => 'skills:list:v3:json',
            ContentExportEnvelope::ENTITY_SERVICES => 'services:list:v3:json',
            default => null,
        };
    }

    public static function downloadFilename(string $entity, string $locale): string
    {
        $slug = str_replace('_', '-', $entity);

        return $slug.'-'.$locale.'-'.now()->format('Y-m-d-His').'.json';
    }
}
