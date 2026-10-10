<?php

namespace App\Services\Appearance;

use App\Services\ContentTypes\ContentTypeDefinition;
use App\Services\ContentTypes\ContentTypeRegistry;
use App\Support\TranslatableContentPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Query published records for the loop_grid widget (eager-loaded, capped, localised).
 */
final class LoopQueryService
{
    private const DEFAULT_SOURCE = 'blog';

    /**
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    public static function items(array $settings, ?string $locale = null): array
    {
        $source = strtolower(trim((string) ($settings['source'] ?? self::DEFAULT_SOURCE)));
        $count = max(1, min(24, (int) ($settings['count'] ?? 6)));
        $order = strtolower(trim((string) ($settings['order'] ?? 'latest')));
        $locale = strtolower(trim((string) $locale));

        $registry = app(ContentTypeRegistry::class);
        if (! self::isConfiguredSource($registry, $source)) {
            $source = self::DEFAULT_SOURCE;
        }
        // Null when the source's module is disabled.
        $definition = $registry->forLoopSource($source);
        if ($definition === null) {
            return [];
        }

        $query = $definition->publishedQuery()->with($definition->eagerLoads())->limit($count);
        TranslatableContentPresenter::scopeQueryForPresentationLocale($query, $locale !== '' ? $locale : null);

        return self::order($query, $order, $definition)
            ->get()
            ->map(fn (Model $record) => self::item($definition, $record, $locale))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function item(ContentTypeDefinition $definition, Model $record, string $locale): array
    {
        $values = $definition->values($record, $locale !== '' ? $locale : null);

        return [
            'id' => $record->getKey(),
            'title' => $values[DynamicTagRegistry::POST_TITLE] ?? '',
            'excerpt' => $values[DynamicTagRegistry::POST_EXCERPT] ?? '',
            'url' => $values[DynamicTagRegistry::POST_URL] ?? '',
            'date' => $values[DynamicTagRegistry::POST_DATE] ?? '',
        ];
    }

    private static function isConfiguredSource(ContentTypeRegistry $registry, string $source): bool
    {
        return in_array($source, array_column($registry->loopSourceOptions(), 'value'), true);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private static function order(Builder $query, string $order, ContentTypeDefinition $definition): Builder
    {
        return match ($order) {
            'oldest' => $query->orderBy('id'),
            'title' => $query->orderBy($definition->titleColumn()),
            default => $query->orderByDesc('id'),
        };
    }
}
