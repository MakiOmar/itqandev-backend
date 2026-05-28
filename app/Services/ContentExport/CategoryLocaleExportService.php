<?php

namespace App\Services\ContentExport;

use App\Models\Category;
use App\Support\ContentExportEnvelope;
use App\Support\TranslatableContentPresenter;
use Illuminate\Support\Collection;

final class CategoryLocaleExportService
{
    /**
     * @param  array<int, int>|null  $ids
     * @return array<string, mixed>
     */
    public function buildEnvelope(string $locale, ?array $ids = null): array
    {
        $categories = $this->categoriesForLocale($locale, $ids);

        $items = $categories->map(function (Category $category) {
            return [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->name,
                'description' => $category->description,
                'is_featured' => (bool) $category->is_featured,
            ];
        })->values()->all();

        return ContentExportEnvelope::build(
            ContentExportEnvelope::ENTITY_CATEGORIES,
            $locale,
            $items,
        );
    }

    /**
     * @param  array<int, int>|null  $ids
     * @return Collection<int, Category>
     */
    public function categoriesForLocale(string $locale, ?array $ids = null): Collection
    {
        $query = Category::query()
            ->with(['translations'])
            ->orderBy('name');

        TranslatableContentPresenter::scopeQueryForPresentationLocale($query, $locale);

        if ($ids !== null && $ids !== []) {
            $query->whereIn('id', $ids);
        }

        $categories = $query->get();

        return $categories
            ->map(function (Category $category) use ($locale) {
                TranslatableContentPresenter::applyCategory($category, $locale);

                return $category;
            })
            ->filter(function (Category $category) use ($locale) {
                return TranslatableContentPresenter::hasCategoryContentForLocale($category, $locale);
            })
            ->values();
    }
}
