<?php

namespace App\Services\ContentTypes;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Declares the dynamic tags of one Eloquent relation on a content type.
 *
 * `many()` yields `{type}.{relation}` (comma-separated list), `.count` and `.first.{field}`;
 * `one()` yields `.{field}`. A URL builder adds `.url` / `.first.url`.
 *
 * Field spec: `suffix => [label, kind = 'text', source = suffix]` where source is an
 * attribute name or a closure receiving the related model.
 *
 * @phpstan-type FieldSpec array{0: string, 1?: string, 2?: string|Closure}
 */
final class RelationTags
{
    /**
     * @param  array<string, FieldSpec>  $fields
     * @param  list<string>  $with
     */
    private function __construct(
        public readonly string $relation,
        public readonly string $label,
        public readonly bool $many,
        private readonly array $fields,
        private readonly ?Closure $url,
        private readonly ?Closure $localize,
        private readonly ?string $listField,
        private readonly ?Closure $filter,
        private readonly array $with,
    ) {}

    /**
     * @param  array<string, FieldSpec>  $fields
     * @param  ?Closure(Model): string  $url
     * @param  ?Closure(Model, string): void  $localize  Applies a translation to a cloned related model.
     * @param  ?string  $listField  Field joined into the `{type}.{relation}` list tag; null skips the list tag.
     * @param  ?Closure(Model): bool  $filter  Keeps only matching related records (e.g. approved testimonials).
     * @param  list<string>  $with  Nested eager loads under the relation (e.g. `media`).
     */
    public static function many(
        string $relation,
        string $label,
        array $fields,
        ?Closure $url = null,
        ?Closure $localize = null,
        ?string $listField = 'name',
        ?Closure $filter = null,
        array $with = [],
    ): self {
        return new self($relation, $label, true, $fields, $url, $localize, $listField, $filter, $with);
    }

    /**
     * @param  array<string, FieldSpec>  $fields
     * @param  ?Closure(Model): string  $url
     * @param  ?Closure(Model, string): void  $localize
     * @param  list<string>  $with
     */
    public static function one(
        string $relation,
        string $label,
        array $fields,
        ?Closure $url = null,
        ?Closure $localize = null,
        array $with = [],
    ): self {
        return new self($relation, $label, false, $fields, $url, $localize, null, null, $with);
    }

    /**
     * @return list<string>
     */
    public function eagerLoads(): array
    {
        $paths = [$this->relation];
        foreach ($this->with as $nested) {
            $paths[] = $this->relation.'.'.$nested;
        }

        return $paths;
    }

    public function group(string $type): TagGroup
    {
        return new TagGroup($this->prefix($type), $this->label, TagGroup::SCOPE_RECORD, $type, $type);
    }

    /**
     * @return list<TagDefinition>
     */
    public function tags(string $type): array
    {
        $prefix = $this->prefix($type);
        $group = $prefix;
        $tags = [];

        if ($this->many) {
            if ($this->listField !== null) {
                $tags[] = new TagDefinition($prefix, $group, $this->label.' (list)');
            }
            $tags[] = new TagDefinition($prefix.'.count', $group, $this->label.' count', 'number');
            $prefix .= '.first';
        }

        foreach ($this->fields as $suffix => $spec) {
            $label = $this->many ? 'First: '.$spec[0] : $spec[0];
            $tags[] = new TagDefinition($prefix.'.'.$suffix, $group, $label, $spec[1] ?? 'text');
        }
        if ($this->url !== null) {
            $tags[] = new TagDefinition($prefix.'.url', $group, $this->many ? 'First: URL' : 'URL', 'url');
        }

        return $tags;
    }

    /**
     * Tag id => value for one (already localised) record; missing relations give empty strings.
     *
     * @return array<string, string>
     */
    public function values(Model $record, string $type, ?string $locale): array
    {
        $prefix = $this->prefix($type);
        $related = $this->related($record, $locale);
        $values = [];

        if ($this->many) {
            if ($this->listField !== null) {
                $values[$prefix] = $related
                    ->map(fn (Model $m) => $this->read($m, $this->listField))
                    ->filter(fn (string $v) => $v !== '')
                    ->implode(', ');
            }
            $values[$prefix.'.count'] = (string) $related->count();
            $prefix .= '.first';
        }

        $first = $related->first();
        foreach ($this->fields as $suffix => $spec) {
            $values[$prefix.'.'.$suffix] = $first instanceof Model ? TagDefinition::read($first, $spec[2] ?? $suffix) : '';
        }
        if ($this->url !== null) {
            $values[$prefix.'.url'] = $first instanceof Model ? TagDefinition::stringify(($this->url)($first)) : '';
        }

        return $values;
    }

    /**
     * Localised related records as a list (one-relations give zero or one item).
     *
     * @return Collection<int, Model>
     */
    private function related(Model $record, ?string $locale): Collection
    {
        $value = $record->getRelationValue($this->relation);
        $items = match (true) {
            $value instanceof Collection => $value,
            $value instanceof Model => collect([$value]),
            default => collect(),
        };
        if ($this->filter !== null) {
            $items = $items->filter($this->filter);
        }

        return $items->map(function (Model $m) use ($locale) {
            if ($this->localize === null || $locale === null || $locale === '') {
                return $m;
            }
            $copy = clone $m;
            ($this->localize)($copy, $locale);

            return $copy;
        })->values();
    }

    private function read(Model $model, string $suffix): string
    {
        return TagDefinition::read($model, $this->fields[$suffix][2] ?? $suffix);
    }

    private function prefix(string $type): string
    {
        return $type.'.'.$this->relation;
    }
}
