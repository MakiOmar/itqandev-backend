<?php

namespace App\Services\ContentExport;

use App\Models\Category;
use App\Services\HtmlSanitizerService;
use App\Support\SiteLanguages;

final class CategoryTranslationSync
{
    public function __construct(
        protected HtmlSanitizerService $sanitizer,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $translations
     */
    public function sync(Category $category, array $translations): void
    {
        $category->refresh();
        $secondaryCodes = SiteLanguages::secondaryLocaleCodesForContent($category->content_locale);
        if ($secondaryCodes === []) {
            return;
        }
        $allowed = array_flip($secondaryCodes);
        $category->translations()->whereNotIn('locale', array_keys($allowed))->delete();

        foreach ($translations as $row) {
            if (! is_array($row)) {
                continue;
            }
            $locale = strtolower(trim((string) ($row['locale'] ?? '')));
            if ($locale === '' || ! isset($allowed[$locale])) {
                continue;
            }

            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            $description = isset($row['description']) ? trim((string) $row['description']) : '';

            if ($name === '' && $description === '') {
                $category->translations()->where('locale', $locale)->delete();

                continue;
            }

            $category->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'name' => $name !== '' ? $name : null,
                    'description' => $description !== '' ? $this->sanitizer->stripAll($description) : null,
                ]
            );
        }
    }
}
