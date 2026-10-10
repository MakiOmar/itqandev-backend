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

    /** Record payload key for many-relation rows; `@` keeps it outside the `{{tag}}` id syntax. */
    public const LISTS_KEY = '@lists';

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

    /** @var array<string, array<string, string>> enabled-type signature => id => kind */
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

    /** Value kind (`text`, `html`, …) of an allowed tag, or null when unknown. */
    public static function kind(string $id): ?string
    {
        return self::idIndex()[$id] ?? null;
    }

    /**
     * Dynamic List sources of enabled content types (`{type}.{relation}:{field}`).
     *
     * @return list<array{value: string, label: string}>
     */
    public static function listSourceOptions(): array
    {
        $options = [];
        foreach (self::contentTypes()->all() as $definition) {
            foreach ($definition->relations() as $relation) {
                array_push($options, ...$relation->listSourceOptions($definition->key(), $definition->label()));
            }
        }

        return $options;
    }

    /**
     * Allowlisted List source split into the `@lists` key and the row field, or null when unknown.
     * An unset source means the first option, matching what the inspector select shows.
     *
     * @return array{source: string, field: string}|null
     */
    public static function parseListSource(string $value): ?array
    {
        $allowed = array_column(self::listSourceOptions(), 'value');
        $value = strtolower(trim($value));
        if ($value === '') {
            $value = (string) ($allowed[0] ?? '');
        }
        if ($value === '' || ! in_array($value, $allowed, true)) {
            return null;
        }
        [$source, $field] = explode(':', $value, 2);

        return ['source' => $source, 'field' => $field];
    }

    /**
     * @return array<string, true>
     */
    private static function idIndex(): array
    {
        $signature = implode(',', self::contentTypes()->keys());

        if (! isset(self::$idIndex[$signature])) {
            $index = [];
            foreach (self::definitions() as $tag) {
                $index[$tag->id] = $tag->kind;
            }
            self::$idIndex[$signature] = $index;
        }

        return self::$idIndex[$signature];
    }

    private static function contentTypes(): ContentTypeRegistry
    {
        return app(ContentTypeRegistry::class);
    }
}
