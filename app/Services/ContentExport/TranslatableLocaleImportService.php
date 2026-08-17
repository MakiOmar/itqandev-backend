<?php

namespace App\Services\ContentExport;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Page;
use App\Models\Project;
use App\Models\Service;
use App\Models\Skill;
use App\Models\Testimonial;
use App\Services\Appearance\PageLayoutDocument;
use App\Services\HtmlSanitizerService;
use App\Support\ContentExportEnvelope;
use App\Support\PageHierarchy;
use App\Support\SiteLanguages;
use App\Support\UniqueContentSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class TranslatableLocaleImportService
{
    public const MODE_UPSERT = 'upsert';

    public const MODE_TRANSLATION_ONLY = 'translation_only';

    public function __construct(
        protected HtmlSanitizerService $sanitizer,
        protected TranslatableTranslationSync $translationSync,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function import(string $entity, array $payload, string $locale, string $mode): array
    {
        ContentExportEnvelope::assertEntity($entity);
        $locale = strtolower(trim($locale));
        ContentExportEnvelope::validate($payload, $entity, $locale);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $items = $payload['items'];
        if ($entity === ContentExportEnvelope::ENTITY_PAGES && is_array($items)) {
            $items = $this->orderPageImportItems($items);
        }

        foreach ($items as $index => $row) {
            if (! is_array($row)) {
                $errors[] = [
                    'id' => null,
                    'slug' => '(row '.($index + 1).')',
                    'message' => 'Invalid item row.',
                ];
                $skipped++;

                continue;
            }

            try {
                $result = $this->importItem($entity, $row, $locale, $mode);
                if ($result === 'created') {
                    $created++;
                } elseif ($result === 'updated') {
                    $updated++;
                } else {
                    $skipped++;
                }
            } catch (ValidationException $e) {
                $errors[] = [
                    'id' => isset($row['id']) ? (int) $row['id'] : null,
                    'slug' => $this->rowLabel($entity, $row, $index),
                    'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
                ];
                $skipped++;
            }
        }

        $prefix = TranslatableLocaleExportService::listCachePrefix($entity);
        if ($prefix !== null) {
            TranslatableListCacheInvalidator::flushPrefix($prefix);
        }

        if ($entity === ContentExportEnvelope::ENTITY_PAGES) {
            Page::bumpPublicCacheVersion();
        }

        return [
            'mode' => $mode,
            'locale' => $locale,
            'entity' => $entity,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     *
     * @return 'created'|'updated'|'skipped'
     */
    private function importItem(string $entity, array $row, string $locale, string $mode): string
    {
        return match ($entity) {
            ContentExportEnvelope::ENTITY_CATEGORIES => $this->importCategory($row, $locale, $mode),
            ContentExportEnvelope::ENTITY_SKILLS => $this->importSkill($row, $locale, $mode),
            ContentExportEnvelope::ENTITY_PROJECTS => $this->importProject($row, $locale, $mode),
            ContentExportEnvelope::ENTITY_SERVICES => $this->importService($row, $locale, $mode),
            ContentExportEnvelope::ENTITY_BLOG_POSTS => $this->importBlogPost($row, $locale, $mode),
            ContentExportEnvelope::ENTITY_TESTIMONIALS => $this->importTestimonial($row, $locale, $mode),
            ContentExportEnvelope::ENTITY_PAGES => $this->importPage($row, $locale, $mode),
            default => throw new \InvalidArgumentException('Unknown entity: '.$entity),
        };
    }

    /**
     * Parents before children so nested creates can resolve parent_slug in one pass.
     *
     * @param  list<mixed>  $items
     * @return list<mixed>
     */
    private function orderPageImportItems(array $items): array
    {
        $indexed = [];
        foreach ($items as $i => $row) {
            if (! is_array($row)) {
                $indexed[] = ['depth' => 0, 'i' => $i, 'row' => $row];

                continue;
            }
            $depth = 0;
            if (! empty($row['parent_id']) || trim((string) ($row['parent_slug'] ?? '')) !== '') {
                $depth = 1;
            }
            $indexed[] = ['depth' => $depth, 'i' => $i, 'row' => $row];
        }
        usort($indexed, function (array $a, array $b) {
            if ($a['depth'] !== $b['depth']) {
                return $a['depth'] <=> $b['depth'];
            }

            return $a['i'] <=> $b['i'];
        });

        return array_map(static fn (array $x) => $x['row'], $indexed);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importCategory(array $row, string $locale, string $mode): string
    {
        $slug = strtolower(trim((string) ($row['slug'] ?? '')));
        $name = trim((string) ($row['name'] ?? ''));
        $description = $this->nullableStripped((string) ($row['description'] ?? ''));
        $isFeatured = (bool) ($row['is_featured'] ?? false);
        $this->assertHasText($name, $description, 'name');

        $category = $this->resolveByIdOrSlug(Category::class, $row, $slug, $mode, true);

        if ($category === null) {
            $this->assertCanCreate($mode, $slug, 'category');
            Category::create([
                'slug' => UniqueContentSlug::suggest(Category::class, $slug),
                'name' => $name !== '' ? $name : $slug,
                'description' => $description,
                'is_featured' => $isFeatured,
                'content_locale' => SiteLanguages::normalizeContentLocale($locale),
            ]);

            return 'created';
        }

        return $this->applyLocaleUpdate(
            $category,
            $locale,
            ['name' => $name, 'description' => $description, 'is_featured' => $isFeatured],
            [['locale' => $locale, 'name' => $name, 'description' => $description ?? '']],
            ['name', 'description'],
            ['description' => fn ($v) => $this->nullableStripped((string) $v)],
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importSkill(array $row, string $locale, string $mode): string
    {
        $slug = strtolower(trim((string) ($row['slug'] ?? '')));
        $name = trim((string) ($row['name'] ?? ''));
        $description = trim((string) ($row['description'] ?? ''));
        $description = $description === '' ? null : $description;
        $iconHint = trim((string) ($row['icon_hint'] ?? ''));
        $this->assertHasText($name, $description, 'name');

        $skill = $this->resolveByIdOrSlug(Skill::class, $row, $slug, $mode, true);

        if ($skill === null) {
            $this->assertCanCreate($mode, $slug, 'skill');
            Skill::create([
                'slug' => UniqueContentSlug::suggest(Skill::class, $slug),
                'name' => $name !== '' ? $name : $slug,
                'description' => $description,
                'icon_hint' => $iconHint !== '' ? $iconHint : null,
                'content_locale' => SiteLanguages::normalizeContentLocale($locale),
            ]);

            return 'created';
        }

        return $this->applyLocaleUpdate(
            $skill,
            $locale,
            [
                'name' => $name !== '' ? $name : $skill->name,
                'description' => $description,
                'icon_hint' => $iconHint !== '' ? $iconHint : $skill->icon_hint,
            ],
            [['locale' => $locale, 'name' => $name, 'description' => $description ?? '']],
            ['name', 'description'],
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importProject(array $row, string $locale, string $mode): string
    {
        $slug = strtolower(trim((string) ($row['slug'] ?? '')));
        $title = trim((string) ($row['title'] ?? ''));
        $summary = $this->nullableStripped((string) ($row['summary'] ?? ''));
        $description = isset($row['description']) ? $this->sanitizer->sanitize((string) $row['description']) : null;
        if ($description === '') {
            $description = null;
        }
        $featured = (bool) ($row['featured'] ?? false);
        $this->assertHasText($title, $summary ?? $description, 'title');

        $project = $this->resolveByIdOrSlug(Project::class, $row, $slug, $mode, true);

        if ($project === null) {
            $this->assertCanCreate($mode, $slug, 'project');
            Project::create([
                'slug' => UniqueContentSlug::suggest(Project::class, $slug),
                'title' => $title !== '' ? $title : $slug,
                'summary' => $summary,
                'description' => $description,
                'featured' => $featured,
                'status' => 'draft',
                'content_locale' => SiteLanguages::normalizeContentLocale($locale),
            ]);

            return 'created';
        }

        return $this->applyLocaleUpdate(
            $project,
            $locale,
            [
                'title' => $title !== '' ? $title : $project->title,
                'summary' => $summary,
                'description' => $description,
                'featured' => $featured,
            ],
            [[
                'locale' => $locale,
                'title' => $title,
                'summary' => $summary ?? '',
                'description' => $description ?? '',
            ]],
            ['title', 'summary', 'description'],
            [
                'summary' => fn ($v) => $this->nullableStripped((string) $v),
                'description' => fn ($v) => $this->sanitizer->sanitize((string) $v),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importService(array $row, string $locale, string $mode): string
    {
        $slug = strtolower(trim((string) ($row['slug'] ?? '')));
        $name = trim((string) ($row['name'] ?? ''));
        $shortDescription = trim((string) ($row['short_description'] ?? ''));
        $shortDescription = $shortDescription === '' ? null : $shortDescription;
        $description = $this->nullableStripped((string) ($row['description'] ?? ''));
        $process = $this->translationSync->normalizeStringList($row['process'] ?? null);
        $deliverables = $this->translationSync->normalizeStringList($row['deliverables'] ?? null);
        $this->assertHasText($name, $shortDescription ?? $description, 'name');

        $service = $this->resolveByIdOrSlug(Service::class, $row, $slug, $mode, true);

        if ($service === null) {
            $this->assertCanCreate($mode, $slug, 'service');
            Service::create([
                'slug' => UniqueContentSlug::suggest(Service::class, $slug),
                'name' => $name !== '' ? $name : $slug,
                'short_description' => $shortDescription,
                'description' => $description,
                'process' => $process !== [] ? $process : null,
                'deliverables' => $deliverables !== [] ? $deliverables : null,
                'is_published' => false,
                'content_locale' => SiteLanguages::normalizeContentLocale($locale),
            ]);

            return 'created';
        }

        return $this->applyLocaleUpdate(
            $service,
            $locale,
            [
                'name' => $name !== '' ? $name : $service->name,
                'short_description' => $shortDescription,
                'description' => $description,
                'process' => $process !== [] ? $process : $service->process,
                'deliverables' => $deliverables !== [] ? $deliverables : $service->deliverables,
            ],
            [[
                'locale' => $locale,
                'name' => $name,
                'short_description' => $shortDescription ?? '',
                'description' => $description ?? '',
                'process' => $process,
                'deliverables' => $deliverables,
            ]],
            ['name', 'short_description', 'description', 'process', 'deliverables'],
            [
                'description' => fn ($v) => $this->nullableStripped((string) $v),
                'process' => fn ($v) => $this->translationSync->normalizeStringList($v),
                'deliverables' => fn ($v) => $this->translationSync->normalizeStringList($v),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importBlogPost(array $row, string $locale, string $mode): string
    {
        $slug = strtolower(trim((string) ($row['slug'] ?? '')));
        $title = trim((string) ($row['title'] ?? ''));
        $excerpt = trim((string) ($row['excerpt'] ?? ''));
        $excerpt = $excerpt === '' ? null : $excerpt;
        $content = isset($row['content']) ? $this->sanitizer->sanitize((string) $row['content']) : null;
        if ($content === '') {
            $content = null;
        }
        $featured = (bool) ($row['featured'] ?? false);
        $this->assertHasText($title, $excerpt ?? $content, 'title');

        $post = $this->resolveByIdOrSlug(BlogPost::class, $row, $slug, $mode, true);

        if ($post === null) {
            $this->assertCanCreate($mode, $slug, 'blog post');
            BlogPost::create([
                'slug' => UniqueContentSlug::suggest(BlogPost::class, $slug),
                'title' => $title !== '' ? $title : $slug,
                'excerpt' => $excerpt,
                'content' => $content ?? '',
                'featured' => $featured,
                'status' => 'draft',
                'content_locale' => SiteLanguages::normalizeContentLocale($locale),
            ]);

            return 'created';
        }

        return $this->applyLocaleUpdate(
            $post,
            $locale,
            [
                'title' => $title !== '' ? $title : $post->title,
                'excerpt' => $excerpt,
                'content' => $content ?? $post->content,
                'featured' => $featured,
            ],
            [[
                'locale' => $locale,
                'title' => $title,
                'excerpt' => $excerpt ?? '',
                'content' => $content ?? '',
            ]],
            ['title', 'excerpt', 'content'],
            ['content' => fn ($v) => $this->sanitizer->sanitize((string) $v)],
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importPage(array $row, string $locale, string $mode): string
    {
        $slug = strtolower(trim((string) ($row['slug'] ?? '')));
        $title = trim((string) ($row['title'] ?? ''));
        $excerpt = trim((string) ($row['excerpt'] ?? ''));
        $excerpt = $excerpt === '' ? null : $excerpt;
        $this->assertHasText($title, $excerpt, 'title');

        $hasSections = array_key_exists('sections', $row);
        $sections = $hasSections
            ? PageLayoutDocument::normalizeSectionsForPages($row['sections'] ?? [])
            : null;

        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if ($status !== Page::STATUS_PUBLISHED && $status !== Page::STATUS_DRAFT) {
            $status = Page::STATUS_DRAFT;
        }
        $excludeFromSearch = (bool) ($row['exclude_from_search'] ?? false);

        $hasParentField = array_key_exists('parent_id', $row) || array_key_exists('parent_slug', $row);
        $parentId = $hasParentField ? $this->resolvePageParentId($row) : null;
        $hasExclude = array_key_exists('exclude_from_search', $row);
        $hasStatus = array_key_exists('status', $row);

        $page = $this->resolveByIdOrSlug(Page::class, $row, $slug, $mode, true);

        if ($page === null) {
            $this->assertCanCreate($mode, $slug, 'page');
            if ($hasParentField && $parentId !== null) {
                PageHierarchy::assertValidParent($parentId, null);
            }
            // Always uniquify on create so re-importing a seed without matching id/slug
            // (or concurrent creates) cannot collide with pages.slug unique index.
            $page = Page::create([
                'slug' => UniqueContentSlug::suggest(Page::class, $slug),
                'title' => $title !== '' ? $title : $slug,
                'excerpt' => $excerpt,
                'status' => $status,
                'published_at' => $status === Page::STATUS_PUBLISHED ? now() : null,
                'parent_id' => $hasParentField ? $parentId : null,
                'exclude_from_search' => $excludeFromSearch,
                'sections' => $sections ?? [],
                'content_locale' => SiteLanguages::normalizeContentLocale($locale),
            ]);
            $this->syncEmbeddedPageTranslations($page, $row, $locale);

            return 'created';
        }

        $primary = SiteLanguages::primaryLocaleForContent($page->content_locale ?? null);
        if ($locale === $primary) {
            if ($hasParentField) {
                PageHierarchy::assertValidParent($parentId, (int) $page->id);
            }
            $update = [
                'title' => $title !== '' ? $title : $page->title,
                'excerpt' => $excerpt,
            ];
            if ($hasStatus) {
                $update['status'] = $status;
                if ($status === Page::STATUS_PUBLISHED && $page->published_at === null) {
                    $update['published_at'] = now();
                }
            }
            if ($hasExclude) {
                $update['exclude_from_search'] = $excludeFromSearch;
            }
            if ($hasParentField) {
                $update['parent_id'] = $parentId;
            }
            if ($hasSections) {
                $update['sections'] = $sections;
            }
            $page->update($update);
            $this->syncEmbeddedPageTranslations($page, $row, $locale);

            return 'updated';
        }

        // Secondary locale: title/excerpt translations; builder layout stays on the main row.
        $this->translationSync->sync(
            $page,
            [['locale' => $locale, 'title' => $title, 'excerpt' => $excerpt ?? '']],
            ['title', 'excerpt'],
        );

        if ($mode === self::MODE_UPSERT && $hasSections) {
            $page->update(['sections' => $sections]);
        }
        $this->syncEmbeddedPageTranslations($page, $row, $locale);

        return 'updated';
    }

    /**
     * Optional per-row `translations: [{ locale, title?, excerpt? }, …]` for one-shot bilingual seeds.
     *
     * @param  array<string, mixed>  $row
     */
    private function syncEmbeddedPageTranslations(Page $page, array $row, string $importLocale): void
    {
        $embedded = $row['translations'] ?? null;
        if (! is_array($embedded) || $embedded === []) {
            return;
        }

        $importLocale = strtolower(trim($importLocale));
        $rows = [];
        foreach ($embedded as $tr) {
            if (! is_array($tr)) {
                continue;
            }
            $loc = strtolower(trim((string) ($tr['locale'] ?? '')));
            if ($loc === '' || $loc === $importLocale) {
                continue;
            }
            $rows[] = [
                'locale' => $loc,
                'title' => trim((string) ($tr['title'] ?? '')),
                'excerpt' => trim((string) ($tr['excerpt'] ?? '')),
            ];
        }

        if ($rows === []) {
            return;
        }

        $this->translationSync->sync($page, $rows, ['title', 'excerpt']);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolvePageParentId(array $row): ?int
    {
        if (array_key_exists('parent_id', $row) && $row['parent_id'] !== null && $row['parent_id'] !== '') {
            $parentId = (int) $row['parent_id'];
            if ($parentId > 0 && Page::query()->whereKey($parentId)->exists()) {
                return $parentId;
            }
        }

        $parentSlug = strtolower(trim((string) ($row['parent_slug'] ?? '')));
        if ($parentSlug === '') {
            return null;
        }

        $parent = Page::query()->where('slug', $parentSlug)->first();
        if ($parent === null) {
            throw ValidationException::withMessages([
                'parent_slug' => ['Parent page slug "'.$parentSlug.'" was not found.'],
            ]);
        }

        return (int) $parent->id;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importTestimonial(array $row, string $locale, string $mode): string
    {
        $content = $this->nullableStripped((string) ($row['content'] ?? ''));
        $clientRole = $this->nullableStripped((string) ($row['client_role'] ?? ''));
        $company = $this->nullableStripped((string) ($row['company'] ?? ''));
        $this->assertHasText($content ?? '', $clientRole ?? $company, 'content');

        $testimonial = $this->resolveByIdOrSlug(Testimonial::class, $row, '', $mode, false);

        if ($testimonial === null) {
            if ($mode === self::MODE_TRANSLATION_ONLY) {
                throw ValidationException::withMessages([
                    'id' => ['Testimonial not found (translation_only mode). Provide a valid id.'],
                ]);
            }

            $clientName = trim((string) ($row['client_name'] ?? ''));
            if ($clientName === '') {
                throw ValidationException::withMessages([
                    'client_name' => ['client_name is required when creating a testimonial.'],
                ]);
            }

            Testimonial::create([
                'project_id' => isset($row['project_id']) ? (int) $row['project_id'] : null,
                'client_name' => $clientName,
                'content' => $content ?? '',
                'client_role' => $clientRole,
                'company' => $company,
                'rating' => min(5, max(1, (int) ($row['rating'] ?? 5))),
                'approved' => false,
                'content_locale' => SiteLanguages::normalizeContentLocale($locale),
            ]);

            return 'created';
        }

        return $this->applyLocaleUpdate(
            $testimonial,
            $locale,
            [
                'content' => $content ?? $testimonial->content,
                'client_role' => $clientRole,
                'company' => $company,
            ],
            [[
                'locale' => $locale,
                'content' => $content ?? '',
                'client_role' => $clientRole ?? '',
                'company' => $company ?? '',
            ]],
            ['content', 'client_role', 'company'],
            [
                'content' => fn ($v) => $this->nullableStripped((string) $v),
                'client_role' => fn ($v) => $this->nullableStripped((string) $v),
                'company' => fn ($v) => $this->nullableStripped((string) $v),
            ],
        );
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $row
     */
    private function resolveByIdOrSlug(
        string $modelClass,
        array $row,
        string $slug,
        string $mode,
        bool $hasSlug,
    ): ?Model {
        $id = isset($row['id']) ? (int) $row['id'] : 0;
        if ($id > 0) {
            $byId = $modelClass::query()->find($id);
            if ($byId !== null) {
                if ($hasSlug && $slug !== '' && strtolower((string) $byId->slug) !== $slug) {
                    throw ValidationException::withMessages([
                        'slug' => ['Slug does not match the record id.'],
                    ]);
                }

                return $byId;
            }

            if ($mode === self::MODE_TRANSLATION_ONLY) {
                throw ValidationException::withMessages([
                    'id' => ['Record not found for id '.$id.'.'],
                ]);
            }
        }

        if (! $hasSlug || $slug === '') {
            return null;
        }

        return $modelClass::query()->where('slug', $slug)->first();
    }

    /**
     * @param  array<string, mixed>  $primaryData
     * @param  array<int, array<string, mixed>>  $translationRows
     * @param  list<string>  $translationFields
     * @param  array<string, callable(mixed): mixed>  $sanitizers
     *
     * @return 'updated'
     */
    private function applyLocaleUpdate(
        Model $model,
        string $locale,
        array $primaryData,
        array $translationRows,
        array $translationFields,
        array $sanitizers = [],
    ): string {
        $primary = SiteLanguages::primaryLocaleForContent($model->content_locale ?? null);

        if ($locale === $primary) {
            $model->update($primaryData);

            return 'updated';
        }

        $this->translationSync->sync($model, $translationRows, $translationFields, $sanitizers);

        return 'updated';
    }

    private function assertHasText(string $primary, ?string $secondary, string $fieldLabel): void
    {
        if ($primary === '' && ($secondary === null || $secondary === '')) {
            throw ValidationException::withMessages([
                $fieldLabel => ['At least one translatable text field is required.'],
            ]);
        }
    }

    private function assertCanCreate(string $mode, string $slug, string $label): void
    {
        if ($mode === self::MODE_TRANSLATION_ONLY) {
            throw ValidationException::withMessages([
                'id' => [ucfirst($label).' not found (translation_only mode).'],
            ]);
        }
        if ($slug === '') {
            throw ValidationException::withMessages([
                'slug' => ['Slug is required when creating a new '.$label.'.'],
            ]);
        }
    }

    private function nullableStripped(string $value): ?string
    {
        $value = $this->sanitizer->stripAll($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowLabel(string $entity, array $row, int $index): string
    {
        $id = isset($row['id']) ? (int) $row['id'] : 0;
        if ($id > 0) {
            return 'id:'.$id;
        }
        if ($entity !== ContentExportEnvelope::ENTITY_TESTIMONIALS) {
            $slug = trim((string) ($row['slug'] ?? ''));
            if ($slug !== '') {
                return $slug;
            }
        }

        return '(row '.($index + 1).')';
    }

    public static function normalizeMode(?string $mode): string
    {
        $mode = strtolower(trim((string) $mode));

        return $mode === self::MODE_TRANSLATION_ONLY
            ? self::MODE_TRANSLATION_ONLY
            : self::MODE_UPSERT;
    }
}
