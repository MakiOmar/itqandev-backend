<?php

namespace App\Services\ContentTypes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One public content type (project, blog post, …): its model, routing, localisation and dynamic tags.
 *
 * Register implementations in config/content-types.php; extend BaseContentType rather than implementing this directly.
 */
interface ContentTypeDefinition
{
    /** Stable key used in theme conditions, chrome type defaults and tag ids (e.g. `project`). */
    public function key(): string;

    public function label(): string;

    public function pluralLabel(): string;

    /** @return class-string<Model> */
    public function modelClass(): string;

    /** Feature module key from config/features.php, or null when always on. */
    public function module(): ?string;

    /** @return Builder<Model> */
    public function publishedQuery(): Builder;

    /** @return list<string> First entry builds publicUrl(); every entry routes `/{prefix}/{slug}`. */
    public function publicPathPrefixes(): array;

    public function publicUrl(Model $record): string;

    /** Canonical public path without the UI locale (`/portfolio/{slug}/`); the site prefixes the locale. */
    public function linkPath(Model $record): string;

    /**
     * Published records whose title or slug matches, for the builder link picker.
     *
     * @return list<array{id: int, title: string, path: string}>
     */
    public function searchLinks(string $query, int $limit): array;

    /** Copy of the record with the locale's translation applied; the given model stays untouched. */
    public function localize(Model $record, string $locale): Model;

    /** @return list<TagGroup> */
    public function groups(): array;

    /** @return list<TagDefinition> Type-specific tags (generic `post.*` tags live in DynamicTagRegistry). */
    public function tags(): array;

    /** @return list<RelationTags> */
    public function relations(): array;

    /** @return list<string> */
    public function eagerLoads(): array;

    /**
     * Image galleries the Gallery widget can use as a dynamic source (suffix => label).
     * Their ordered media ids are published in `values()` under `DynamicTagRegistry::GALLERIES_KEY`.
     *
     * @return array<string, string>
     */
    public function galleries(): array;

    /** @return list<int> Media ids of one gallery from `galleries()`, in display order. */
    public function galleryMediaIds(Model $record, string $suffix): array;

    /**
     * Tag id => value (generic `post.*` plus this type's own tags), localised when a locale is given.
     * `DynamicTagRegistry::LISTS_KEY` holds many-relation rows (`{type}.{relation}` => list of field => value) for dynamic Lists.
     *
     * @return array<string, mixed>
     */
    public function values(Model $record, ?string $locale = null): array;

    /** Column the loop grid sorts by for "Title" order. */
    public function titleColumn(): string;

    /** loop_grid `source` value, or null when the type cannot be listed in a loop. */
    public function loopSource(): ?string;

    /** @return array{context: string, page_slug: string, label: string}|null Listing route context, its CMS page slug and fallback title. */
    public function archive(): ?array;

    public function findPublishedBySlug(string $slug): ?Model;

    /**
     * SEO for the generic `/{prefix}/{slug}` single, from an already localised record: optional `title`,
     * `description` and `image` overrides plus `json_ld` (one schema.org object or a list of them).
     *
     * @return array{title?: string, description?: string, image?: string, json_ld?: array<mixed>}
     */
    public function seo(Model $record, string $locale): array;

    public function findForPreview(int $id): ?Model;

    /** @return list<array{id: int, title: string}> Latest records (any status) for the builder preview picker. */
    public function previewOptions(int $limit): array;
}
