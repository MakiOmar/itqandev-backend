<?php

namespace App\Services\Appearance;

use App\Services\ContentTypes\ContentTypeRegistry;
use App\Services\ContentTypes\TagDefinition;
use App\Services\ContentTypes\TagGroup;

/**
 * Allowlisted dynamic tags for theme / loop documents.
 *
 * Static `site.*`, generic `post.*` (every content type) and `archive.*` tags, plus the
 * type-specific groups declared by each content type in config/content-types.php.
 */
final class DynamicTagRegistry
{
    public const GROUP_SITE = 'site';

    public const GROUP_RECORD = 'record';

    public const GROUP_ARCHIVE = 'archive';

    public const POST_TITLE = 'post.title';

    public const POST_SUBTITLE = 'post.subtitle';

    public const POST_EXCERPT = 'post.excerpt';

    public const POST_CONTENT = 'post.content';

    public const POST_URL = 'post.url';

    public const POST_IMAGE = 'post.image';

    public const POST_DATE = 'post.date';

    public const POST_TERMS = 'post.terms';

    public const ARCHIVE_TITLE = 'archive.title';

    /** Generic record tags: `post.{suffix}` => [label, kind]. */
    public const POST_FIELDS = [
        'title' => ['Title', 'text'],
        'subtitle' => ['Subtitle', 'text'],
        'excerpt' => ['Excerpt', 'text'],
        'content' => ['Content', 'html'],
        'url' => ['Permalink', 'url'],
        'image' => ['Featured image URL', 'image'],
        'date' => ['Date', 'date'],
        'terms' => ['Terms', 'text'],
    ];

    private const SITE_FIELDS = [
        'site.name' => ['Site name', 'text'],
        'site.tagline' => ['Site tagline', 'text'],
        'site.url' => ['Site URL', 'url'],
        'site.logo' => ['Site logo URL', 'image'],
        'site.year' => ['Current year', 'number'],
    ];

    /** @var array<string, array<string, true>> enabled-type signature => id index */
    private static array $idIndex = [];

    /**
     * @return list<array{id: string, group: string, label: string, kind: string}>
     */
    public static function all(): array
    {
        return array_map(static fn (TagDefinition $t) => $t->toArray(), self::definitions());
    }

    /**
     * @return list<TagDefinition>
     */
    public static function definitions(): array
    {
        $tags = [];
        foreach (self::SITE_FIELDS as $id => [$label, $kind]) {
            $tags[] = new TagDefinition($id, self::GROUP_SITE, $label, $kind);
        }
        foreach (self::POST_FIELDS as $suffix => [$label, $kind]) {
            $tags[] = new TagDefinition('post.'.$suffix, self::GROUP_RECORD, $label, $kind);
        }
        $tags[] = new TagDefinition(self::ARCHIVE_TITLE, self::GROUP_ARCHIVE, 'Archive title');
        foreach (self::contentTypes()->all() as $definition) {
            array_push($tags, ...$definition->tags());
        }

        return $tags;
    }

    /**
     * Dropdown headings; content type groups nest their relation subgroups via `parent`.
     *
     * @return list<array{id: string, label: string, parent: string|null, scope: string, content_type: string|null}>
     */
    public static function groups(): array
    {
        $groups = [
            new TagGroup(self::GROUP_SITE, 'Site', TagGroup::SCOPE_SITE),
            new TagGroup(self::GROUP_RECORD, 'Current item', TagGroup::SCOPE_RECORD),
            new TagGroup(self::GROUP_ARCHIVE, 'Archive', TagGroup::SCOPE_ARCHIVE),
        ];
        foreach (self::contentTypes()->all() as $definition) {
            array_push($groups, ...$definition->groups());
        }

        return array_map(static fn (TagGroup $g) => $g->toArray(), $groups);
    }

    /**
     * @return list<string>
     */
    public static function ids(): array
    {
        return array_keys(self::idIndex());
    }

    public static function isAllowed(string $id): bool
    {
        return isset(self::idIndex()[$id]);
    }

    /**
     * @return array<string, true>
     */
    private static function idIndex(): array
    {
        $signature = implode(',', self::contentTypes()->keys());

        return self::$idIndex[$signature] ??= array_fill_keys(
            array_map(static fn (TagDefinition $t) => $t->id, self::definitions()),
            true
        );
    }

    private static function contentTypes(): ContentTypeRegistry
    {
        return app(ContentTypeRegistry::class);
    }
}
