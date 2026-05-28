<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Services\ContentExport\TranslatableLocaleExportService;
use App\Services\ContentExport\TranslatableLocaleImportService;
use App\Support\ContentExportEnvelope;
use App\Support\TranslatableContentPresenter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsImportsTranslatableContent
{
    abstract protected function exportImportEntity(): string;

    abstract protected function exportImportPolicyModel(): string;

    public function export(Request $request, TranslatableLocaleExportService $exportService): StreamedResponse
    {
        $this->authorize('viewAny', $this->exportImportPolicyModel());

        $entity = $this->exportImportEntity();
        $locale = $this->requirePresentationLocale($request);

        $table = $this->exportImportExistsTable();
        $data = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer', 'exists:'.$table.',id'],
        ]);

        $ids = isset($data['ids']) && is_array($data['ids']) ? array_map('intval', $data['ids']) : null;

        $envelope = $exportService->buildEnvelope($entity, $locale, $ids);
        $filename = TranslatableLocaleExportService::downloadFilename($entity, $locale);

        return response()->streamDownload(function () use ($envelope) {
            echo json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }, $filename, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public function import(Request $request, TranslatableLocaleImportService $importService)
    {
        $this->authorize('viewAny', $this->exportImportPolicyModel());

        $entity = $this->exportImportEntity();
        $locale = $this->requirePresentationLocale($request);

        $meta = $request->validate([
            'mode' => ['nullable', 'string', 'in:upsert,translation_only'],
        ]);

        $payload = $request->json()->all();
        if ($payload === []) {
            $payload = $request->all();
        }

        $mode = TranslatableLocaleImportService::normalizeMode($meta['mode'] ?? null);

        $result = $importService->import($entity, $payload, $locale, $mode);

        return response()->json($result);
    }

    protected function exportImportExistsTable(): string
    {
        return match ($this->exportImportEntity()) {
            ContentExportEnvelope::ENTITY_CATEGORIES => 'categories',
            ContentExportEnvelope::ENTITY_SKILLS => 'skills',
            ContentExportEnvelope::ENTITY_PROJECTS => 'projects',
            ContentExportEnvelope::ENTITY_SERVICES => 'services',
            ContentExportEnvelope::ENTITY_BLOG_POSTS => 'blog_posts',
            ContentExportEnvelope::ENTITY_TESTIMONIALS => 'testimonials',
            default => throw new \InvalidArgumentException('Unknown entity'),
        };
    }

    private function requirePresentationLocale(Request $request): string
    {
        $locale = TranslatableContentPresenter::requestedPresentationLocale($request);
        if ($locale === null || $locale === '') {
            throw ValidationException::withMessages([
                'X-Content-Locale' => ['A valid X-Content-Locale header is required for export/import.'],
            ]);
        }

        return $locale;
    }
}
