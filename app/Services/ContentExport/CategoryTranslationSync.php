<?php

namespace App\Services\ContentExport;

use App\Models\Category;
use App\Services\HtmlSanitizerService;
final class CategoryTranslationSync
{
    public function __construct(
        protected HtmlSanitizerService $sanitizer,
        protected TranslatableTranslationSync $generic,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $translations
     */
    public function sync(Category $category, array $translations): void
    {
        $this->generic->sync(
            $category,
            $translations,
            ['name', 'description'],
            ['description' => fn ($v) => $this->sanitizer->stripAll((string) $v)],
        );
    }
}
