<?php

namespace App\Services\Appearance;

use App\Services\ContentTypes\ContentTypeDefinition;
use App\Services\ContentTypes\ContentTypeRegistry;
use App\Support\TranslatableContentPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Query published records for the loop_grid widget (eager-loaded, capped, localised).
 *
 * With `loop_item_id` each item carries the loop item layout filled with that record's tags.
 */
final class LoopQueryService
{
    private const DEFAULT_SOURCE = 'blog';

    /** Loop grids nested inside a loop item layout render nothing (no unbounded recursion). */
    private static int $depth = 0;

    /**
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    public static function items(array $settings, ?string $locale = null): array
    {
        if (self::$depth > 0) {
            return [];
        }

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

        $loopItemId = (int) ($settings['loop_item_id'] ?? 0);

        return self::order($query, $order, $definition)
            ->get()
            ->map(fn (Model $record) => self::item($definition, $record, $locale, $loopItemId))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function item(ContentTypeDefinition $definition, Model $record, string $locale, int $loopItemId): array
    {
        $values = $definition->values($record, $locale !== '' ? $locale : null);
        $item = [
            'id' => $record->getKey(),
            'title' => $values[DynamicTagRegistry::POST_TITLE] ?? '',
            'excerpt' => $values[DynamicTagRegistry::POST_EXCERPT] ?? '',
            'url' => $values[DynamicTagRegistry::POST_URL] ?? '',
            'date' => $values[DynamicTagRegistry::POST_DATE] ?? '',
        ];
        if ($loopItemId > 0) {
            $sections = self::loopItemSections($loopItemId, $locale, $values);
            if ($sections !== []) {
                $item['layout'] = ['sections' => self::suffixNodeIds($sections, '-r'.$record->getKey())];
            }
        }

        return $item;
    }

    /**
     * @param  array<string, string>  $values
     * @return list<array<string, mixed>>
     */
    private static function loopItemSections(int $loopItemId, string $locale, array $values): array
    {
        self::$depth++;
        try {
            return app(ChromeLayoutService::class)->presentLoopItem($loopItemId, $locale !== '' ? $locale : null, $values)['sections'];
        } finally {
            self::$depth--;
        }
    }

    /**
     * Repeated layouts would share band/row/column/block ids; suffix them per record so DOM ids stay unique.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private static function suffixNodeIds(array $nodes, string $suffix): array
    {
        foreach ($nodes as &$node) {
            if (! is_array($node)) {
                continue;
            }
            if (is_string($node['id'] ?? null) && $node['id'] !== '') {
                $node['id'] .= $suffix;
            }
            foreach (['rows', 'columns', 'blocks'] as $children) {
                if (is_array($node[$children] ?? null)) {
                    $node[$children] = self::suffixNodeIds($node[$children], $suffix);
                }
            }
        }
        unset($node);

        return $nodes;
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
