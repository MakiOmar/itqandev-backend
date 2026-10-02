<?php

namespace App\Services\Appearance;

use App\Models\BuilderGlobal;
use App\Support\UniqueContentSlug;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class GlobalWidgetService
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): BuilderGlobal
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Name is required.']);
        }
        $document = $this->normalizeLeaf($input['document'] ?? $input);
        if ($document === null) {
            throw ValidationException::withMessages(['document' => 'A widget or kit document is required.']);
        }

        $slug = UniqueContentSlug::fromSource(
            BuilderGlobal::class,
            (string) ($input['slug'] ?? $name)
        );

        return BuilderGlobal::query()->create([
            'name' => $name,
            'slug' => $slug !== '' ? $slug : 'global-'.Str::lower(Str::random(6)),
            'status' => $this->status($input['status'] ?? BuilderGlobal::STATUS_DRAFT),
            'document' => $document,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(BuilderGlobal $global, array $input): BuilderGlobal
    {
        if (array_key_exists('name', $input)) {
            $name = trim((string) $input['name']);
            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'Name is required.']);
            }
            $global->name = $name;
        }
        if (array_key_exists('status', $input)) {
            $global->status = $this->status($input['status']);
        }
        if (array_key_exists('document', $input) || array_key_exists('type', $input)) {
            $document = $this->normalizeLeaf($input['document'] ?? $input);
            if ($document === null) {
                throw ValidationException::withMessages(['document' => 'A widget or kit document is required.']);
            }
            $global->document = $document;
        }
        $global->save();

        return $global->fresh();
    }

    /**
     * Inline published globals into a layout tree. Missing ids are skipped.
     *
     * @param  list<array<string, mixed>>  $sections
     * @param  array<int, true>  $stack
     * @return list<array<string, mixed>>
     */
    public static function resolveSections(array $sections, array $stack = []): array
    {
        foreach ($sections as &$band) {
            if (! is_array($band)) {
                continue;
            }
            $band['rows'] = self::resolveRows(is_array($band['rows'] ?? null) ? $band['rows'] : [], $stack);
        }
        unset($band);

        return $sections;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, true>  $stack
     * @return list<array<string, mixed>>
     */
    private static function resolveRows(array $rows, array $stack): array
    {
        foreach ($rows as &$row) {
            if (! is_array($row)) {
                continue;
            }
            $columns = is_array($row['columns'] ?? null) ? $row['columns'] : [];
            foreach ($columns as &$col) {
                if (! is_array($col)) {
                    continue;
                }
                $col['blocks'] = self::resolveBlocks(is_array($col['blocks'] ?? null) ? $col['blocks'] : [], $stack);
            }
            unset($col);
            $row['columns'] = $columns;
        }
        unset($row);

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  array<int, true>  $stack
     * @return list<array<string, mixed>>
     */
    private static function resolveBlocks(array $blocks, array $stack): array
    {
        $resolved = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            $leaf = self::resolveLeaf($block, $stack);
            if ($leaf === null) {
                continue;
            }
            if (($leaf['type'] ?? '') === PageLayoutDocument::TYPE_INNER_BAND) {
                $leaf['rows'] = self::resolveRows(is_array($leaf['rows'] ?? null) ? $leaf['rows'] : [], $stack);
            }
            $resolved[] = $leaf;
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<int, true>  $stack
     * @return array<string, mixed>|null
     */
    public static function resolveLeaf(array $block, array $stack = []): ?array
    {
        $kind = strtolower(trim((string) ($block['kind'] ?? '')));
        if ($kind !== 'global') {
            return $block;
        }
        $id = (int) ($block['global_id'] ?? 0);
        if ($id < 1 || isset($stack[$id])) {
            return null;
        }
        if (! \Illuminate\Support\Facades\Schema::hasTable('builder_globals')) {
            return null;
        }
        $global = BuilderGlobal::query()->find($id);
        if ($global === null || ! $global->isPublished()) {
            return null;
        }
        $doc = is_array($global->document) ? $global->document : [];
        $innerKind = strtolower(trim((string) ($doc['kind'] ?? '')));
        if ($innerKind === 'global') {
            $stack[$id] = true;

            return self::resolveLeaf($doc, $stack);
        }
        $doc['id'] = (string) ($block['id'] ?? $doc['id'] ?? ('glb_'.$id));
        $doc['global_source_id'] = $id;

        return $doc;
    }

    /**
     * @param  mixed  $raw
     * @return array<string, mixed>|null
     */
    private function normalizeLeaf(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $leaf = is_array($raw['document'] ?? null) ? $raw['document'] : $raw;
        if (strtolower(trim((string) ($leaf['kind'] ?? ''))) === 'global') {
            throw ValidationException::withMessages(['document' => 'Globals cannot nest other globals.']);
        }
        return PageLayoutDocument::normalizeLeafForGlobal($leaf);
    }

    private function status(mixed $status): string
    {
        $status = strtolower(trim((string) $status));

        return $status === BuilderGlobal::STATUS_PUBLISHED
            ? BuilderGlobal::STATUS_PUBLISHED
            : BuilderGlobal::STATUS_DRAFT;
    }
}
