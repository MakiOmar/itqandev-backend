<?php

namespace App\Services\ContentExport;

use App\Models\Category;
use App\Services\HtmlSanitizerService;
use App\Support\ContentExportEnvelope;
use App\Support\SiteLanguages;
use App\Support\UniqueContentSlug;
use Illuminate\Validation\ValidationException;

final class CategoryLocaleImportService
{
    public const MODE_UPSERT = 'upsert';

    public const MODE_TRANSLATION_ONLY = 'translation_only';

    public function __construct(
        protected HtmlSanitizerService $sanitizer,
        protected CategoryTranslationSync $translationSync,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function import(array $payload, string $locale, string $mode): array
    {
        $locale = strtolower(trim($locale));
        ContentExportEnvelope::validate($payload, ContentExportEnvelope::ENTITY_CATEGORIES, $locale);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $items = $payload['items'];
        foreach ($items as $index => $row) {
            if (! is_array($row)) {
                $errors[] = [
                    'slug' => '(row '.($index + 1).')',
                    'message' => 'Invalid item row.',
                ];
                $skipped++;

                continue;
            }

            try {
                $result = $this->importItem($row, $locale, $mode);
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
                    'slug' => $this->rowLabel($row, $index),
                    'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
                ];
                $skipped++;
            }
        }

        CategoryListCacheInvalidator::flush();

        return [
            'mode' => $mode,
            'locale' => $locale,
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
    private function importItem(array $row, string $locale, string $mode): string
    {
        $slug = strtolower(trim((string) ($row['slug'] ?? '')));

        $name = trim((string) ($row['name'] ?? ''));
        $description = isset($row['description']) ? $this->sanitizer->stripAll((string) $row['description']) : null;
        if ($description === '') {
            $description = null;
        }
        $isFeatured = (bool) ($row['is_featured'] ?? false);

        if ($name === '' && ($description === null || $description === '')) {
            throw ValidationException::withMessages([
                'name' => ['Name or description is required.'],
            ]);
        }

        $category = $this->resolveCategory($row, $slug, $mode);

        if ($category === null) {
            if ($mode === self::MODE_TRANSLATION_ONLY) {
                throw ValidationException::withMessages([
                    'id' => ['Category not found (translation_only mode). Provide a valid id or slug.'],
                ]);
            }

            if ($slug === '') {
                throw ValidationException::withMessages([
                    'slug' => ['Slug is required when creating a new category.'],
                ]);
            }

            $uniqueSlug = UniqueContentSlug::suggest(Category::class, $slug);
            Category::create([
                'slug' => $uniqueSlug,
                'name' => $name !== '' ? $name : $slug,
                'description' => $description,
                'is_featured' => $isFeatured,
                'content_locale' => SiteLanguages::normalizeContentLocale($locale),
            ]);

            return 'created';
        }

        $primary = SiteLanguages::primaryLocaleForContent($category->content_locale);

        if ($locale === $primary) {
            $category->update([
                'name' => $name !== '' ? $name : $category->name,
                'description' => $description,
                'is_featured' => $isFeatured,
            ]);

            return 'updated';
        }

        $this->translationSync->sync($category, [
            [
                'locale' => $locale,
                'name' => $name,
                'description' => $description ?? '',
            ],
        ]);

        return 'updated';
    }

    /**
     * Resolve an existing category by id (preferred) or slug.
     *
     * @param  array<string, mixed>  $row
     */
    private function resolveCategory(array $row, string $slug, string $mode): ?Category
    {
        $id = isset($row['id']) ? (int) $row['id'] : 0;
        if ($id > 0) {
            $byId = Category::query()->find($id);
            if ($byId !== null) {
                if ($slug !== '' && strtolower($byId->slug) !== $slug) {
                    throw ValidationException::withMessages([
                        'slug' => ['Slug does not match the category id.'],
                    ]);
                }

                return $byId;
            }

            if ($mode === self::MODE_TRANSLATION_ONLY) {
                throw ValidationException::withMessages([
                    'id' => ['Category not found for id '.$id.'.'],
                ]);
            }
        }

        if ($slug === '') {
            return null;
        }

        return Category::query()->where('slug', $slug)->first();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowLabel(array $row, int $index): string
    {
        $id = isset($row['id']) ? (int) $row['id'] : 0;
        if ($id > 0) {
            return 'id:'.$id;
        }
        $slug = trim((string) ($row['slug'] ?? ''));

        return $slug !== '' ? $slug : '(row '.($index + 1).')';
    }

    public static function normalizeMode(?string $mode): string
    {
        $mode = strtolower(trim((string) $mode));

        return $mode === self::MODE_TRANSLATION_ONLY
            ? self::MODE_TRANSLATION_ONLY
            : self::MODE_UPSERT;
    }
}
