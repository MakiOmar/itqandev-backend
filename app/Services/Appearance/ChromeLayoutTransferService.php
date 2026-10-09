<?php

namespace App\Services\Appearance;

use App\Models\ChromeLayout;
use Illuminate\Validation\ValidationException;

/**
 * JSON export/import and bulk operations for Theme Builder layouts (`chrome_layouts`).
 *
 * Every write goes through ChromeLayoutService so slug uniquifying, document
 * normalization, revisions, cache busting, and delete/unpublish guards still apply.
 */
final class ChromeLayoutTransferService
{
    public const FORMAT = 'credocode.chrome-layouts-export';

    public const VERSION = 1;

    public function __construct(
        private readonly ChromeLayoutService $layouts,
    ) {}

    /**
     * @param  list<int>|null  $ids  Limit to these ids; null exports every layout of the kind.
     * @return array<string, mixed>
     */
    public function export(string $kind, ?array $ids = null): array
    {
        $items = ChromeLayout::query()
            ->kind($kind)
            ->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))
            ->orderBy('name')
            ->get()
            ->map(fn (ChromeLayout $layout) => [
                'name' => $layout->name,
                'slug' => $layout->slug,
                'status' => $layout->status,
                'document' => $this->layouts->adminDocumentPayload($layout),
            ])
            ->values()
            ->all();

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'kind' => $kind,
            'exported_at' => now()->toIso8601String(),
            'items' => $items,
        ];
    }

    /**
     * Upsert by slug within the kind. Existing layouts keep their status so imports
     * never unpublish a layout that pages or templates rely on.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array{created: int, updated: int, skipped: int, errors: list<array{slug: string, message: string}>}
     */
    public function import(string $kind, array $items): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($items as $item) {
            $slug = (string) ($item['slug'] ?? '');
            $payload = [
                'name' => (string) $item['name'],
                'document' => (array) $item['document'],
            ];

            try {
                $existing = $slug !== ''
                    ? ChromeLayout::query()->kind($kind)->where('slug', $slug)->first()
                    : null;

                if ($existing !== null) {
                    $this->layouts->update($existing, $payload);
                    $result['updated']++;

                    continue;
                }

                $this->layouts->create($kind, [
                    ...$payload,
                    'slug' => $slug,
                    'status' => $item['status'] ?? ChromeLayout::STATUS_DRAFT,
                ]);
                $result['created']++;
            } catch (ValidationException $e) {
                $result['skipped']++;
                $result['errors'][] = ['slug' => $slug ?: (string) $item['name'], 'message' => $this->firstError($e)];
            }
        }

        return $result;
    }

    /**
     * Deletes one by one so the site-default and in-use guards apply per layout.
     *
     * @param  list<int>  $ids
     * @return array{deleted: int, skipped: int, errors: list<array{id: int, name: string, message: string}>}
     */
    public function bulkDelete(string $kind, array $ids): array
    {
        $result = ['deleted' => 0, 'skipped' => 0, 'errors' => []];

        foreach (ChromeLayout::query()->kind($kind)->whereIn('id', $ids)->get() as $layout) {
            try {
                $this->layouts->delete($layout);
                $result['deleted']++;
            } catch (ValidationException $e) {
                $result['skipped']++;
                $result['errors'][] = ['id' => (int) $layout->id, 'name' => $layout->name, 'message' => $this->firstError($e)];
            }
        }

        return $result;
    }

    /**
     * @param  list<int>  $ids
     * @return array{updated: int, skipped: int, errors: list<array{id: int, name: string, message: string}>}
     */
    public function bulkSetStatus(string $kind, array $ids, string $status): array
    {
        $result = ['updated' => 0, 'skipped' => 0, 'errors' => []];

        foreach (ChromeLayout::query()->kind($kind)->whereIn('id', $ids)->get() as $layout) {
            if ($layout->status === $status) {
                continue;
            }
            try {
                $this->layouts->update($layout, ['status' => $status]);
                $result['updated']++;
            } catch (ValidationException $e) {
                $result['skipped']++;
                $result['errors'][] = ['id' => (int) $layout->id, 'name' => $layout->name, 'message' => $this->firstError($e)];
            }
        }

        return $result;
    }

    private function firstError(ValidationException $e): string
    {
        $first = collect($e->errors())->flatten()->first();

        return is_string($first) ? $first : $e->getMessage();
    }
}
