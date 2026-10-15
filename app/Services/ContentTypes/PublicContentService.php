<?php

namespace App\Services\ContentTypes;

use App\Addons\AddonRegistry;
use App\Services\Appearance\DynamicTagRegistry;

/**
 * Registry-driven public reads for content types that have no dedicated website route (addon types).
 */
final class PublicContentService
{
    private const SITEMAP_LIMIT_PER_TYPE = 5000;

    public function __construct(private readonly ContentTypeRegistry $contentTypes) {}

    /**
     * Head data for a published single at `/{prefix}/{slug}`, or null when nothing matches.
     *
     * @return array{type: string, id: int, title: string, excerpt: string, image: string, path: string}|null
     */
    public function single(string $prefix, string $slug, ?string $locale): ?array
    {
        $match = $this->contentTypes->forPath('/'.strtolower($prefix).'/'.$slug);
        if ($match === null) {
            return null;
        }
        [$definition, $slug] = $match;
        $record = $definition->findPublishedBySlug($slug);
        if ($record === null) {
            return null;
        }
        $values = $definition->values($record, $locale);
        $text = static fn (string $tag): string => is_scalar($values[$tag] ?? null) ? (string) $values[$tag] : '';

        return [
            'type' => $definition->key(),
            'id' => (int) $record->getKey(),
            'title' => $text(DynamicTagRegistry::POST_TITLE),
            'excerpt' => trim(strip_tags($text(DynamicTagRegistry::POST_EXCERPT))),
            'image' => $text(DynamicTagRegistry::POST_IMAGE),
            'path' => $definition->linkPath($record),
        ];
    }

    /**
     * Published paths of enabled addon content types. Built-in types keep their own sitemap entries.
     *
     * @return list<string>
     */
    public function addonSitemapPaths(): array
    {
        $paths = [];
        foreach ($this->contentTypes->all() as $definition) {
            $module = $definition->module();
            if ($module === null || ! AddonRegistry::enabled($module)) {
                continue;
            }
            $records = $definition->publishedQuery()->limit(self::SITEMAP_LIMIT_PER_TYPE)->get();
            foreach ($records as $record) {
                $path = $definition->linkPath($record);
                if ($path !== '') {
                    $paths[] = $path;
                }
            }
        }

        return array_values(array_unique($paths));
    }
}
