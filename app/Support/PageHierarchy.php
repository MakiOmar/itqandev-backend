<?php

namespace App\Support;

use App\Models\Page;
use Illuminate\Validation\ValidationException;

/**
 * Nested CMS page paths and parent-cycle guards.
 */
final class PageHierarchy
{
    public const MAX_DEPTH = 8;

    /**
     * @param  iterable<int, Page>  $pages
     * @return array<int, string> page id → slug path (parent/child)
     */
    public static function pathsById(iterable $pages): array
    {
        $byId = self::indexById($pages);
        $out = [];
        foreach ($byId as $id => $page) {
            $out[$id] = self::pathFor($page, $byId);
        }

        return $out;
    }

    /**
     * @param  array<int, Page>  $byId
     */
    public static function pathFor(Page $page, array $byId = []): string
    {
        $segments = [];
        $current = $page;
        $guard = 0;
        while ($current !== null && $guard++ < self::MAX_DEPTH) {
            $slug = trim((string) $current->slug);
            if ($slug !== '') {
                array_unshift($segments, $slug);
            }
            $parentId = $current->parent_id ? (int) $current->parent_id : null;
            if ($parentId === null) {
                break;
            }
            $current = $byId[$parentId] ?? null;
            if ($current === null && $parentId > 0) {
                $current = Page::query()->select(['id', 'slug', 'parent_id'])->find($parentId);
                if ($current !== null) {
                    $byId[$parentId] = $current;
                }
            }
        }

        return implode('/', $segments);
    }

    /**
     * @param  iterable<int, Page>  $pages
     * @return array<int, Page>
     */
    public static function indexById(iterable $pages): array
    {
        $byId = [];
        foreach ($pages as $page) {
            $byId[(int) $page->id] = $page;
        }

        return $byId;
    }

    /**
     * @return list<int>
     */
    public static function descendantIds(int $pageId): array
    {
        $ids = [];
        $frontier = [$pageId];
        $guard = 0;
        while ($frontier !== [] && $guard++ < 500) {
            $children = Page::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            if ($children === []) {
                break;
            }
            foreach ($children as $id) {
                $ids[] = $id;
            }
            $frontier = $children;
        }

        return $ids;
    }

    public static function depthOf(?int $pageId): int
    {
        if ($pageId === null || $pageId < 1) {
            return 0;
        }
        $depth = 0;
        $current = Page::query()->select(['id', 'parent_id'])->find($pageId);
        while ($current?->parent_id && $depth < self::MAX_DEPTH + 1) {
            $current = Page::query()->select(['id', 'parent_id'])->find((int) $current->parent_id);
            $depth++;
        }

        return $depth;
    }

    public static function subtreeHeight(?int $pageId): int
    {
        if ($pageId === null || $pageId < 1) {
            return 0;
        }
        $height = 0;
        $frontier = [$pageId];
        while ($frontier !== [] && $height < self::MAX_DEPTH + 1) {
            $children = Page::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            if ($children === []) {
                break;
            }
            $height++;
            $frontier = $children;
        }

        return $height;
    }

    public static function assertValidParent(?int $parentId, ?int $pageId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($pageId !== null && $parentId === $pageId) {
            throw ValidationException::withMessages([
                'parent_id' => ['A page cannot be its own parent.'],
            ]);
        }

        if (! Page::query()->whereKey($parentId)->exists()) {
            throw ValidationException::withMessages([
                'parent_id' => ['The selected parent page does not exist.'],
            ]);
        }

        if ($pageId !== null && in_array($parentId, self::descendantIds($pageId), true)) {
            throw ValidationException::withMessages([
                'parent_id' => ['The selected parent would create a nested-page cycle.'],
            ]);
        }

        $parentDepth = self::depthOf($parentId);
        $extra = $pageId !== null ? self::subtreeHeight($pageId) : 0;
        if ($parentDepth + 1 + $extra >= self::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'parent_id' => ['Nested pages cannot be deeper than '.self::MAX_DEPTH.' levels.'],
            ]);
        }
    }

    /**
     * Flatten for admin tables: parents before children, with depth.
     *
     * @param  Collection<int, Page>|list<Page>  $pages
     * @return list<Page>
     */
    public static function flattenForAdmin(iterable $pages): array
    {
        $all = [];
        foreach ($pages as $page) {
            $all[(int) $page->id] = $page;
        }
        $children = [];
        foreach ($all as $page) {
            $pid = $page->parent_id ? (int) $page->parent_id : 0;
            $children[$pid][] = $page;
        }
        foreach ($children as &$group) {
            usort($group, function (Page $a, Page $b) {
                return strcasecmp((string) $a->title, (string) $b->title);
            });
        }
        unset($group);

        $out = [];
        $seen = [];
        $walk = function (int $parentKey, int $depth) use (&$walk, &$out, &$seen, $children): void {
            foreach ($children[$parentKey] ?? [] as $page) {
                $id = (int) $page->id;
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $page->setAttribute('depth', $depth);
                $out[] = $page;
                $walk($id, $depth + 1);
            }
        };
        $walk(0, 0);

        foreach ($all as $page) {
            $id = (int) $page->id;
            if (isset($seen[$id])) {
                continue;
            }
            $page->setAttribute('depth', 0);
            $out[] = $page;
        }

        return $out;
    }
}
