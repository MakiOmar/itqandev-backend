<?php

namespace App\Services\ContentTypes;

use App\Services\Appearance\DynamicTagRegistry;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

/**
 * Shared behaviour so each content type only declares data.
 *
 * `postFields()` maps `post.*` suffixes to a source; `fields()` maps own suffixes to `[label, kind = 'text', source = suffix]`.
 * A source is an attribute name or a closure receiving the (localised) model.
 *
 * @phpstan-import-type FieldSpec from RelationTags
 */
abstract class BaseContentType implements ContentTypeDefinition
{
    /** @var list<TagDefinition>|null */
    private ?array $tagCache = null;

    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function pluralLabel(): string;

    abstract public function modelClass(): string;

    abstract public function publicPathPrefixes(): array;

    /** Apply the locale's translation row onto the (already cloned) record. */
    abstract protected function applyTranslation(Model $record, string $locale): void;

    public function module(): ?string
    {
        return null;
    }

    public function publishedQuery(): Builder
    {
        return $this->modelClass()::query()->where('status', 'published');
    }

    public function publicUrl(Model $record): string
    {
        return '/'.$this->publicPathPrefixes()[0].'/'.$record->getAttribute('slug');
    }

    public function linkPath(Model $record): string
    {
        $slug = trim((string) $record->getAttribute('slug'));

        return $slug === '' ? '' : '/'.$this->publicPathPrefixes()[0].'/'.$slug.'/';
    }

    public function searchLinks(string $query, int $limit): array
    {
        $title = $this->titleColumn();
        $builder = $this->publishedQuery();
        $term = trim($query);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '\\%_').'%';
            $builder->where(static fn (Builder $q) => $q->where($title, 'like', $like)->orWhere('slug', 'like', $like));
        }

        $links = [];
        foreach ($builder->orderBy($title)->limit($limit)->get() as $record) {
            $path = $this->linkPath($record);
            if ($path !== '') {
                $links[] = [
                    'id' => (int) $record->getKey(),
                    'title' => (string) ($record->getAttribute($title) ?: $record->getAttribute('slug')),
                    'path' => $path,
                ];
            }
        }

        return $links;
    }

    public function localize(Model $record, string $locale): Model
    {
        $copy = clone $record;
        $this->applyTranslation($copy, $locale);

        return $copy;
    }

    /**
     * Generic `post.*` sources keyed by DynamicTagRegistry::POST_FIELDS suffix; `url` always comes from publicUrl().
     *
     * @return array<string, string|Closure>
     */
    protected function postFields(): array
    {
        return ['title' => 'title', 'excerpt' => 'excerpt', 'content' => 'content'];
    }

    /**
     * Type-specific tags `{key}.{suffix}`.
     *
     * @return array<string, FieldSpec>
     */
    protected function fields(): array
    {
        return [];
    }

    public function relations(): array
    {
        return [];
    }

    /** Relation whose localised list fills `post.terms` (e.g. `categories`). */
    protected function termsRelation(): ?string
    {
        return null;
    }

    /** @return list<string> Extra eager loads besides relations (e.g. `media` for image tags). */
    protected function extraEagerLoads(): array
    {
        return [];
    }

    public function galleries(): array
    {
        return [];
    }

    public function galleryMediaIds(Model $record, string $suffix): array
    {
        return [];
    }

    public function eagerLoads(): array
    {
        $paths = $this->extraEagerLoads();
        foreach ($this->relations() as $relation) {
            array_push($paths, ...$relation->eagerLoads());
        }

        return array_values(array_unique($paths));
    }

    public function loopSource(): ?string
    {
        return null;
    }

    public function archive(): ?array
    {
        return null;
    }

    public function titleColumn(): string
    {
        $title = $this->postFields()['title'] ?? 'title';

        return is_string($title) ? $title : 'title';
    }

    public function groups(): array
    {
        $groups = [new TagGroup($this->key(), $this->label(), TagGroup::SCOPE_RECORD, null, $this->key())];
        foreach ($this->relations() as $relation) {
            $groups[] = $relation->group($this->key());
        }

        return $groups;
    }

    public function tags(): array
    {
        if ($this->tagCache !== null) {
            return $this->tagCache;
        }

        $tags = [];
        foreach ($this->fields() as $suffix => $spec) {
            $tags[] = new TagDefinition($this->key().'.'.$suffix, $this->key(), $spec[0], $spec[1] ?? 'text');
        }
        foreach ($this->relations() as $relation) {
            array_push($tags, ...$relation->tags($this->key()));
        }

        return $this->tagCache = $tags;
    }

    public function values(Model $record, ?string $locale = null): array
    {
        $locale = strtolower(trim((string) $locale));
        if ($locale !== '') {
            $record = $this->localize($record, $locale);
        }

        $values = $this->postValues($record);
        foreach ($this->fields() as $suffix => $spec) {
            $values[$this->key().'.'.$suffix] = TagDefinition::read($record, $spec[2] ?? $suffix);
        }
        $lists = [];
        foreach ($this->relations() as $relation) {
            $values += $relation->values($record, $this->key(), $locale);
            if ($relation->many) {
                $lists[$this->key().'.'.$relation->relation] = $relation->listItems($record, $locale);
            }
        }
        $values[DynamicTagRegistry::LISTS_KEY] = $lists;
        $galleries = [];
        foreach (array_keys($this->galleries()) as $suffix) {
            $galleries[$this->key().'.'.$suffix] = $this->galleryMediaIds($record, $suffix);
        }
        $values[DynamicTagRegistry::GALLERIES_KEY] = $galleries;
        $terms = $this->termsRelation();
        if ($terms !== null) {
            $values[DynamicTagRegistry::POST_TERMS] = $values[$this->key().'.'.$terms] ?? '';
        }

        return $values;
    }

    public function findPublishedBySlug(string $slug): ?Model
    {
        return $this->publishedQuery()->where('slug', $slug)->first();
    }

    public function findForPreview(int $id): ?Model
    {
        return $id > 0 ? $this->modelClass()::query()->find($id) : null;
    }

    public function previewOptions(int $limit): array
    {
        $title = $this->titleColumn();

        return $this->modelClass()::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', $title])
            ->map(static fn (Model $m) => ['id' => (int) $m->getKey(), 'title' => (string) ($m->getAttribute($title) ?: $m->getKey())])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function postValues(Model $record): array
    {
        $sources = $this->postFields();
        $values = [];
        foreach (array_keys(DynamicTagRegistry::POST_FIELDS) as $suffix) {
            $id = 'post.'.$suffix;
            if ($suffix === 'url') {
                $values[$id] = $this->publicUrl($record);

                continue;
            }
            $values[$id] = isset($sources[$suffix]) ? TagDefinition::read($record, $sources[$suffix]) : '';
        }

        return $values;
    }

    /** Absolute URL of the first media item in a collection, or '' when missing. */
    protected static function mediaUrl(Model $record, string $collection): string
    {
        if (! $record instanceof HasMedia) {
            return '';
        }
        $url = $record->getFirstMediaUrl($collection);
        if ($url === '') {
            return '';
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : url($url);
    }
}
