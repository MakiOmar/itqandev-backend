<?php

namespace App\Services\Appearance;

/**
 * Allowlisted dynamic tags for theme / loop documents.
 */
final class DynamicTagRegistry
{
    /**
     * @return list<array{id: string, group: string, label: string}>
     */
    public static function all(): array
    {
        return [
            ['id' => 'site.name', 'group' => 'site', 'label' => 'Site name'],
            ['id' => 'site.tagline', 'group' => 'site', 'label' => 'Site tagline'],
            ['id' => 'site.url', 'group' => 'site', 'label' => 'Site URL'],
            ['id' => 'site.logo', 'group' => 'site', 'label' => 'Site logo URL'],
            ['id' => 'site.year', 'group' => 'site', 'label' => 'Current year'],
            ['id' => 'post.title', 'group' => 'record', 'label' => 'Title'],
            ['id' => 'post.subtitle', 'group' => 'record', 'label' => 'Subtitle'],
            ['id' => 'post.excerpt', 'group' => 'record', 'label' => 'Excerpt'],
            ['id' => 'post.content', 'group' => 'record', 'label' => 'Content'],
            ['id' => 'post.url', 'group' => 'record', 'label' => 'Permalink'],
            ['id' => 'post.image', 'group' => 'record', 'label' => 'Featured image URL'],
            ['id' => 'post.date', 'group' => 'record', 'label' => 'Date'],
            ['id' => 'post.terms', 'group' => 'record', 'label' => 'Terms'],
            ['id' => 'archive.title', 'group' => 'archive', 'label' => 'Archive title'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function ids(): array
    {
        return array_column(self::all(), 'id');
    }

    public static function isAllowed(string $id): bool
    {
        return in_array($id, self::ids(), true);
    }
}
