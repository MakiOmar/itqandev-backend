<?php

namespace App\Services\Appearance;

use App\Models\BlogPost;
use App\Models\ChromeLayout;
use App\Models\Page;
use App\Models\Project;
use App\Models\Service;
use App\Support\MarketingSettingsCache;
use App\Support\ProjectSettingsStore;
use App\Support\SiteLanguages;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ChromeLayoutService
{
    public const TYPE_DEFAULTS_KEY = 'chrome_type_defaults';

    public const CACHE_SECONDS = 300;

    /** @var list<string> */
    public const CONTENT_TYPES = ['homepage', 'page', 'project', 'blog_post', 'service'];

    public function defaultDocument(string $kind): array
    {
        return $kind === ChromeLayout::KIND_FOOTER
            ? app(FooterBuilderService::class)->defaultDocument()
            : app(HeaderBuilderService::class)->defaultDocument();
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array{sections: list<array<string, mixed>>}
     */
    public function normalizeDocument(array $document): array
    {
        return ChromeLayoutSupport::normalizeDocument($document);
    }

    public function list(string $kind, bool $includeDocument = false, int $perPage = 50): LengthAwarePaginator
    {
        $columns = ['id', 'kind', 'name', 'slug', 'status', 'is_site_default', 'created_at', 'updated_at'];
        if ($includeDocument) {
            $columns[] = 'document';
        }

        return ChromeLayout::query()
            ->kind($kind)
            ->orderByDesc('is_site_default')
            ->orderBy('name')
            ->paginate(max(1, min($perPage, 100)), $columns);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(string $kind, array $input): ChromeLayout
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Name is required.']);
        }

        $slug = $this->normalizeSlug((string) ($input['slug'] ?? ''), $name);
        $this->assertUniqueSlug($kind, $slug);

        $status = $this->normalizeStatus($input['status'] ?? ChromeLayout::STATUS_DRAFT);
        $document = $this->normalizeDocument(
            isset($input['sections']) || isset($input['document'])
                ? (isset($input['sections']) ? ['sections' => $input['sections']] : (array) $input['document'])
                : $this->defaultDocument($kind)
        );
        if (($document['sections'] ?? []) === []) {
            $document = $this->defaultDocument($kind);
        }

        $layout = ChromeLayout::query()->create([
            'kind' => $kind,
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
            'document' => $document,
            'is_site_default' => false,
        ]);

        MarketingSettingsCache::forgetAll();
        $this->forgetLayoutCache((int) $layout->id);

        return $layout;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(ChromeLayout $layout, array $input): ChromeLayout
    {
        if (array_key_exists('name', $input)) {
            $name = trim((string) $input['name']);
            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'Name is required.']);
            }
            $layout->name = $name;
        }

        if (array_key_exists('slug', $input)) {
            $slug = $this->normalizeSlug((string) $input['slug'], $layout->name);
            $this->assertUniqueSlug($layout->kind, $slug, (int) $layout->id);
            $layout->slug = $slug;
        }

        if (array_key_exists('status', $input)) {
            $nextStatus = $this->normalizeStatus($input['status']);
            if ($nextStatus === ChromeLayout::STATUS_DRAFT && $layout->isPublished()) {
                $this->assertCanUnpublish($layout);
            }
            $layout->status = $nextStatus;
        }

        if (array_key_exists('sections', $input) || array_key_exists('document', $input)) {
            $raw = array_key_exists('sections', $input)
                ? ['sections' => $input['sections']]
                : (array) $input['document'];
            $document = $this->normalizeDocument($raw);
            if (($document['sections'] ?? []) === []) {
                $document = $this->defaultDocument($layout->kind);
            }
            $layout->document = $document;
        }

        $layout->save();

        MarketingSettingsCache::forgetAll();
        $this->forgetLayoutCache((int) $layout->id);

        return $layout->fresh();
    }

    public function delete(ChromeLayout $layout): void
    {
        if ($layout->is_site_default) {
            throw ValidationException::withMessages([
                'layout' => 'Cannot delete the site-default '.$layout->kind.' layout. Promote another layout first.',
            ]);
        }

        $deps = $this->dependentLabels($layout);
        if ($deps !== []) {
            throw ValidationException::withMessages([
                'layout' => 'Cannot delete this layout; it is still used as: '.implode(', ', $deps).'.',
            ]);
        }

        $id = (int) $layout->id;
        $layout->delete();

        MarketingSettingsCache::forgetAll();
        $this->forgetLayoutCache($id);
    }

    /**
     * Normalize optional header/footer assignment fields on content payloads.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function applyAssignmentFields(array $data): array
    {
        if (array_key_exists('header_layout_id', $data)) {
            $data['header_layout_id'] = $this->assertAssignableId(
                $data['header_layout_id'],
                ChromeLayout::KIND_HEADER,
                'header_layout_id'
            );
        }
        if (array_key_exists('footer_layout_id', $data)) {
            $data['footer_layout_id'] = $this->assertAssignableId(
                $data['footer_layout_id'],
                ChromeLayout::KIND_FOOTER,
                'footer_layout_id'
            );
        }

        return $data;
    }

    public function assertAssignableId(mixed $id, string $kind, string $field): ?int
    {
        if ($id === null || $id === '' || $id === false) {
            return null;
        }
        if (! is_numeric($id)) {
            throw ValidationException::withMessages([$field => 'Invalid layout id.']);
        }
        $layout = ChromeLayout::query()->find((int) $id);
        if ($layout === null || $layout->kind !== $kind || ! $layout->isPublished()) {
            throw ValidationException::withMessages([
                $field => 'Must reference a published '.$kind.' layout.',
            ]);
        }

        return (int) $layout->id;
    }

    public function setSiteDefault(ChromeLayout $layout): ChromeLayout
    {
        if (! $layout->isPublished()) {
            throw ValidationException::withMessages([
                'status' => 'Only published layouts can be set as the site default.',
            ]);
        }

        DB::transaction(function () use ($layout) {
            ChromeLayout::query()
                ->kind($layout->kind)
                ->where('id', '!=', $layout->id)
                ->where('is_site_default', true)
                ->update(['is_site_default' => false]);

            $layout->is_site_default = true;
            $layout->save();
        });

        MarketingSettingsCache::forgetAll();
        $this->forgetAllLayoutCaches();

        return $layout->fresh();
    }

    /**
     * @return array{sections: list<array<string, mixed>>}
     */
    public function presentById(int $id, ?string $locale = null): array
    {
        $locale = $locale !== null && $locale !== ''
            ? strtolower(trim($locale))
            : SiteLanguages::defaultCode();

        $cacheKey = $this->layoutCacheKey($id, $locale);

        /** @var array{sections: list<array<string, mixed>>} $presented */
        $presented = Cache::remember($cacheKey, self::CACHE_SECONDS, function () use ($id, $locale) {
            $layout = ChromeLayout::query()->find($id);
            if ($layout === null || ! $layout->isPublished()) {
                return ['sections' => []];
            }
            $document = is_array($layout->document) ? $layout->document : ['sections' => []];

            return ChromeLayoutSupport::presentPublic($document, $locale);
        });

        return $presented;
    }

    public function forgetLayoutCache(int $id): void
    {
        foreach ($this->cacheLocales() as $locale) {
            Cache::forget($this->layoutCacheKey($id, $locale));
        }
    }

    public function forgetAllLayoutCaches(): void
    {
        if (! Schema::hasTable('chrome_layouts')) {
            return;
        }

        $ids = ChromeLayout::query()->pluck('id');
        foreach ($ids as $id) {
            $this->forgetLayoutCache((int) $id);
        }
    }

    public function findSiteDefault(string $kind): ?ChromeLayout
    {
        if (! Schema::hasTable('chrome_layouts')) {
            return null;
        }

        return ChromeLayout::query()
            ->kind($kind)
            ->published()
            ->siteDefault()
            ->first();
    }

    /**
     * @return array<string, array{header_id: int|null, footer_id: int|null}>
     */
    public function getTypeDefaults(): array
    {
        $stored = ProjectSettingsStore::load();
        $raw = is_array($stored[self::TYPE_DEFAULTS_KEY] ?? null)
            ? $stored[self::TYPE_DEFAULTS_KEY]
            : [];

        return $this->normalizeTypeDefaults($raw);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array{header_id: int|null, footer_id: int|null}>
     */
    public function saveTypeDefaults(array $input): array
    {
        $normalized = $this->normalizeTypeDefaults($input);
        $this->assertTypeDefaultIdsValid($normalized);
        ProjectSettingsStore::merge([self::TYPE_DEFAULTS_KEY => $normalized]);
        MarketingSettingsCache::forgetAll();
        $this->forgetAllLayoutCaches();

        return $normalized;
    }

    /**
     * @return array{sections: list<array<string, mixed>>}
     */
    public function adminDocumentPayload(ChromeLayout $layout): array
    {
        $document = is_array($layout->document) ? $layout->document : ['sections' => []];

        return $this->normalizeDocument($document);
    }

    /**
     * @return list<string>
     */
    public function dependentLabels(ChromeLayout $layout): array
    {
        $labels = [];

        if ($layout->is_site_default) {
            $labels[] = 'site default '.$layout->kind;
        }

        $typeDefaults = $this->getTypeDefaults();
        $idKey = $layout->kind === ChromeLayout::KIND_FOOTER ? 'footer_id' : 'header_id';
        foreach ($typeDefaults as $contentType => $pair) {
            if (($pair[$idKey] ?? null) === (int) $layout->id) {
                $labels[] = 'type default for '.$contentType;
            }
        }

        $headerCol = 'header_layout_id';
        $footerCol = 'footer_layout_id';
        $col = $layout->kind === ChromeLayout::KIND_FOOTER ? $footerCol : $headerCol;

        foreach (
            [
                'pages' => Page::class,
                'projects' => Project::class,
                'blog posts' => BlogPost::class,
                'services' => Service::class,
            ] as $label => $modelClass
        ) {
            /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
            if (! Schema::hasTable((new $modelClass)->getTable())) {
                continue;
            }
            if (! Schema::hasColumn((new $modelClass)->getTable(), $col)) {
                continue;
            }
            $count = $modelClass::query()->where($col, $layout->id)->count();
            if ($count > 0) {
                $labels[] = $count.' '.$label;
            }
        }

        return $labels;
    }

    private function assertCanUnpublish(ChromeLayout $layout): void
    {
        $deps = $this->dependentLabels($layout);
        if ($deps === []) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'Cannot unpublish this layout; it is still used as: '.implode(', ', $deps).'.',
        ]);
    }

    private function normalizeSlug(string $slug, string $fallbackName): string
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            $slug = Str::slug($fallbackName);
        }
        $slug = Str::slug($slug);
        if ($slug === '') {
            $slug = 'layout-'.Str::lower(Str::random(6));
        }

        return $slug;
    }

    private function assertUniqueSlug(string $kind, string $slug, ?int $ignoreId = null): void
    {
        $query = ChromeLayout::query()->kind($kind)->where('slug', $slug);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages([
                'slug' => 'A '.$kind.' layout with this slug already exists.',
            ]);
        }
    }

    private function normalizeStatus(mixed $status): string
    {
        $status = strtolower(trim((string) $status));
        if (! in_array($status, [ChromeLayout::STATUS_DRAFT, ChromeLayout::STATUS_PUBLISHED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Status must be draft or published.',
            ]);
        }

        return $status;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, array{header_id: int|null, footer_id: int|null}>
     */
    private function normalizeTypeDefaults(array $raw): array
    {
        $out = [];
        foreach (self::CONTENT_TYPES as $type) {
            $row = is_array($raw[$type] ?? null) ? $raw[$type] : [];
            $headerId = $row['header_id'] ?? null;
            $footerId = $row['footer_id'] ?? null;
            $out[$type] = [
                'header_id' => $headerId === null || $headerId === '' ? null : (int) $headerId,
                'footer_id' => $footerId === null || $footerId === '' ? null : (int) $footerId,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array{header_id: int|null, footer_id: int|null}>  $defaults
     */
    private function assertTypeDefaultIdsValid(array $defaults): void
    {
        foreach ($defaults as $contentType => $pair) {
            foreach (['header_id' => ChromeLayout::KIND_HEADER, 'footer_id' => ChromeLayout::KIND_FOOTER] as $key => $kind) {
                $id = $pair[$key] ?? null;
                if ($id === null) {
                    continue;
                }
                $layout = ChromeLayout::query()->find($id);
                if ($layout === null || $layout->kind !== $kind || ! $layout->isPublished()) {
                    throw ValidationException::withMessages([
                        $contentType.'.'.$key => 'Must reference a published '.$kind.' layout.',
                    ]);
                }
            }
        }
    }

    private function layoutCacheKey(int $id, string $locale): string
    {
        return 'chrome:layout:'.$id.':'.$locale;
    }

    /**
     * @return list<string>
     */
    private function cacheLocales(): array
    {
        $locales = array_unique(array_merge(
            [SiteLanguages::defaultCode(), 'en', 'ar'],
            array_map(
                fn ($row) => strtolower((string) ($row['code'] ?? '')),
                SiteLanguages::all()
            ),
        ));

        $out = [];
        foreach ($locales as $locale) {
            $locale = strtolower(trim((string) $locale));
            if ($locale !== '') {
                $out[] = $locale;
            }
        }

        return array_values(array_unique($out));
    }
}
