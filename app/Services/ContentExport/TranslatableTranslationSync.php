<?php

namespace App\Services\ContentExport;

use App\Services\HtmlSanitizerService;
use App\Support\SiteLanguages;
use Illuminate\Database\Eloquent\Model;

final class TranslatableTranslationSync
{
    public function __construct(
        protected HtmlSanitizerService $sanitizer,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $translations
     * @param  list<string>  $translatableFields
     * @param  array<string, callable(mixed): mixed>  $sanitizers  field => sanitizer
     */
    public function sync(
        Model $model,
        array $translations,
        array $translatableFields,
        array $sanitizers = [],
    ): void {
        $model->refresh();
        $secondaryCodes = SiteLanguages::secondaryLocaleCodesForContent($model->content_locale ?? null);
        if ($secondaryCodes === []) {
            return;
        }
        $allowed = array_flip($secondaryCodes);
        $model->translations()->whereNotIn('locale', array_keys($allowed))->delete();

        foreach ($translations as $row) {
            if (! is_array($row)) {
                continue;
            }
            $locale = strtolower(trim((string) ($row['locale'] ?? '')));
            if ($locale === '' || ! isset($allowed[$locale])) {
                continue;
            }

            $payload = [];
            $allEmpty = true;
            foreach ($translatableFields as $field) {
                $value = $this->normalizeFieldValue($row, $field, $sanitizers);
                if ($this->fieldHasContent($value)) {
                    $allEmpty = false;
                }
                $payload[$field] = $value;
            }

            if ($allEmpty) {
                $model->translations()->where('locale', $locale)->delete();

                continue;
            }

            foreach ($payload as $field => $value) {
                if (! $this->fieldHasContent($value)) {
                    $payload[$field] = null;
                }
            }

            $model->translations()->updateOrCreate(['locale' => $locale], $payload);
        }
    }

    /**
     * @param  array<string, callable(mixed): mixed>  $sanitizers
     */
    private function normalizeFieldValue(array $row, string $field, array $sanitizers): mixed
    {
        if (! array_key_exists($field, $row)) {
            return null;
        }

        $value = $row[$field];
        if (isset($sanitizers[$field])) {
            return ($sanitizers[$field])($value);
        }

        if (is_array($value)) {
            return $this->normalizeStringList($value);
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        return $value;
    }

    private function fieldHasContent(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_array($value)) {
            return count($value) > 0;
        }

        return $value !== null;
    }

    /**
     * @return list<string>
     */
    public function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }
            $t = trim($item);
            if ($t !== '') {
                $out[] = $t;
            }
        }

        return $out;
    }
}
