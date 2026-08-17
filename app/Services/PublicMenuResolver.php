<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Project;
use App\Models\Service;
use App\Models\Skill;
use App\Support\CmsPublicPaths;
use App\Support\PageHierarchy;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class PublicMenuResolver
{
    private const CACHE_SECONDS = 300;

    public static function forgetCacheForMenu(Menu $menu): void
    {
        foreach (SiteLanguages::all() as $row) {
            $code = strtolower((string) ($row['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            Cache::forget(self::cacheKey($menu->slug, $code));
        }
    }

    /**
     * @return list<array{label: string, href: string, open_in_new_tab: bool, children: list<mixed>}>
     */
    public static function resolvePublishedTree(string $menuSlug, string $locale): array
    {
        $locale = strtolower(trim($locale));
        if ($locale === '') {
            $locale = SiteLanguages::defaultCode();
        }

        $cacheKey = self::cacheKey($menuSlug, $locale);

        /** @var list<array{label: string, href: string, open_in_new_tab: bool, children: list<mixed>}> $tree */
        $tree = Cache::remember($cacheKey, now()->addSeconds(self::CACHE_SECONDS), function () use ($menuSlug, $locale) {
            $menu = Menu::query()->where('slug', $menuSlug)->first();
            if ($menu === null) {
                return [];
            }

            $items = $menu->items()->with('translations')->orderBy('sort_order')->get();
            $references = self::preloadMenuReferences($items, $locale);
            $pageGraph = PageHierarchy::indexById(
                Page::query()->select(['id', 'slug', 'parent_id'])->get()
            );

            return self::buildResolvedTree($items, null, $locale, $references, $pageGraph);
        });

        return $tree;
    }

    private static function cacheKey(string $menuSlug, string $locale): string
    {
        return 'public_menu:'.$menuSlug.':'.$locale;
    }

    /**
     * @param  Collection<int, MenuItem>  $items
     * @return array<string, array<int, Model>>
     */
    private static function preloadMenuReferences(Collection $items, string $locale): array
    {
        $idsByType = [
            MenuItem::TYPE_PROJECT => [],
            MenuItem::TYPE_BLOG_POST => [],
            MenuItem::TYPE_SERVICE => [],
            MenuItem::TYPE_CATEGORY => [],
            MenuItem::TYPE_SKILL => [],
            MenuItem::TYPE_PAGE => [],
        ];

        foreach ($items as $item) {
            if (! isset($idsByType[$item->item_type]) || ! $item->reference_id) {
                continue;
            }
            $idsByType[$item->item_type][] = (int) $item->reference_id;
        }

        $out = [];

        if ($idsByType[MenuItem::TYPE_PROJECT] !== []) {
            $out[MenuItem::TYPE_PROJECT] = self::indexById(
                Project::query()
                    ->select(['id', 'title', 'slug', 'content_locale'])
                    ->with('translations')
                    ->whereIn('id', array_unique($idsByType[MenuItem::TYPE_PROJECT]))
                    ->get()
                    ->each(fn (Project $p) => TranslatableContentPresenter::applyProject($p, $locale))
            );
        }

        if ($idsByType[MenuItem::TYPE_BLOG_POST] !== []) {
            $out[MenuItem::TYPE_BLOG_POST] = self::indexById(
                BlogPost::query()
                    ->select(['id', 'title', 'slug', 'content_locale'])
                    ->with('translations')
                    ->whereIn('id', array_unique($idsByType[MenuItem::TYPE_BLOG_POST]))
                    ->get()
                    ->each(fn (BlogPost $p) => TranslatableContentPresenter::applyBlogPost($p, $locale))
            );
        }

        if ($idsByType[MenuItem::TYPE_SERVICE] !== []) {
            $out[MenuItem::TYPE_SERVICE] = self::indexById(
                Service::query()
                    ->select(['id', 'name', 'slug', 'content_locale'])
                    ->with('translations')
                    ->whereIn('id', array_unique($idsByType[MenuItem::TYPE_SERVICE]))
                    ->get()
                    ->each(fn (Service $s) => TranslatableContentPresenter::applyService($s, $locale))
            );
        }

        if ($idsByType[MenuItem::TYPE_CATEGORY] !== []) {
            $out[MenuItem::TYPE_CATEGORY] = self::indexById(
                Category::query()
                    ->select(['id', 'name', 'slug', 'content_locale'])
                    ->with('translations')
                    ->whereIn('id', array_unique($idsByType[MenuItem::TYPE_CATEGORY]))
                    ->get()
                    ->each(fn (Category $c) => TranslatableContentPresenter::applyCategory($c, $locale))
            );
        }

        if ($idsByType[MenuItem::TYPE_SKILL] !== []) {
            $out[MenuItem::TYPE_SKILL] = self::indexById(
                Skill::query()
                    ->select(['id', 'name', 'slug', 'content_locale'])
                    ->with('translations')
                    ->whereIn('id', array_unique($idsByType[MenuItem::TYPE_SKILL]))
                    ->get()
                    ->each(fn (Skill $s) => TranslatableContentPresenter::applySkill($s, $locale))
            );
        }

        if ($idsByType[MenuItem::TYPE_PAGE] !== []) {
            $out[MenuItem::TYPE_PAGE] = self::indexById(
                Page::query()
                    ->select(['id', 'title', 'slug', 'content_locale', 'status', 'parent_id'])
                    ->with('translations')
                    ->where('status', Page::STATUS_PUBLISHED)
                    ->whereIn('id', array_unique($idsByType[MenuItem::TYPE_PAGE]))
                    ->get()
                    ->each(function (Page $p) use ($locale) {
                        TranslatableContentPresenter::applyPage($p, $locale);
                    })
                    ->filter(fn (Page $p) => TranslatableContentPresenter::hasPageContentForLocale($p, $locale))
                    ->values()
            );
        }

        return $out;
    }

    /**
     * @param  Collection<int, Model>  $models
     * @return array<int, Model>
     */
    private static function indexById(Collection $models): array
    {
        $indexed = [];
        foreach ($models as $model) {
            $indexed[(int) $model->getKey()] = $model;
        }

        return $indexed;
    }

    /**
     * @param  Collection<int, MenuItem>  $all
     * @param  array<string, array<int, Model>>  $references
     * @param  array<int, Page>  $pageGraph
     * @return list<array{label: string, href: string, open_in_new_tab: bool, children: list<mixed>}>
     */
    private static function buildResolvedTree(Collection $all, ?int $parentId, string $locale, array $references, array $pageGraph): array
    {
        $out = [];
        foreach ($all->where('parent_id', $parentId)->sortBy('sort_order') as $item) {
            $resolved = self::resolveItem($item, $locale, $references, $pageGraph);
            if ($resolved === null) {
                continue;
            }
            $resolved['children'] = self::buildResolvedTree($all, $item->id, $locale, $references, $pageGraph);
            $out[] = $resolved;
        }

        return $out;
    }

    /**
     * @param  array<string, array<int, Model>>  $references
     * @param  array<int, Page>  $pageGraph
     * @return array{label: string, href: string, open_in_new_tab: bool, children: array}|null
     */
    private static function resolveItem(MenuItem $item, string $locale, array $references, array $pageGraph): ?array
    {
        $label = self::resolvedLabel($item, $locale);
        $href = null;

        switch ($item->item_type) {
            case MenuItem::TYPE_CUSTOM_LINK:
                $raw = (string) ($item->url ?? '');
                if (trim($raw) === '') {
                    return null;
                }
                if (preg_match('#^https?://#i', $raw)) {
                    $href = $raw;
                } else {
                    $path = '/'.ltrim($raw, '/');
                    $href = self::prefixLocale($locale, $path === '//' ? '/' : $path);
                }
                $label ??= $href;

                break;

            case MenuItem::TYPE_PROJECT:
                $project = self::lookupReference($references, MenuItem::TYPE_PROJECT, $item->reference_id);
                if (! $project instanceof Project) {
                    return null;
                }
                $slug = (string) $project->slug;
                if ($slug === '') {
                    return null;
                }
                $href = self::prefixLocale($locale, '/portfolio/'.$slug.'/');
                $label ??= (string) $project->title;

                break;

            case MenuItem::TYPE_BLOG_POST:
                $post = self::lookupReference($references, MenuItem::TYPE_BLOG_POST, $item->reference_id);
                if (! $post instanceof BlogPost) {
                    return null;
                }
                $slug = (string) $post->slug;
                if ($slug === '') {
                    return null;
                }
                $href = self::prefixLocale($locale, '/blog/'.$slug.'/');
                $label ??= (string) $post->title;

                break;

            case MenuItem::TYPE_SERVICE:
                $service = self::lookupReference($references, MenuItem::TYPE_SERVICE, $item->reference_id);
                if (! $service instanceof Service) {
                    return null;
                }
                $slug = (string) $service->slug;
                if ($slug === '') {
                    return null;
                }
                $href = self::prefixLocale($locale, '/services/'.$slug.'/');
                $label ??= (string) $service->name;

                break;

            case MenuItem::TYPE_CATEGORY:
                $category = self::lookupReference($references, MenuItem::TYPE_CATEGORY, $item->reference_id);
                if (! $category instanceof Category) {
                    return null;
                }
                $slug = (string) $category->slug;
                if ($slug === '') {
                    return null;
                }
                $href = self::prefixLocale($locale, '/portfolio/?category_slug='.rawurlencode($slug));
                $label ??= (string) $category->name;

                break;

            case MenuItem::TYPE_SKILL:
                $skill = self::lookupReference($references, MenuItem::TYPE_SKILL, $item->reference_id);
                if (! $skill instanceof Skill) {
                    return null;
                }
                $slug = (string) $skill->slug;
                if ($slug === '') {
                    return null;
                }
                $href = self::prefixLocale($locale, '/portfolio/?skill_slug='.rawurlencode($slug));
                $label ??= (string) $skill->name;

                break;

            case MenuItem::TYPE_PAGE:
                $page = self::lookupReference($references, MenuItem::TYPE_PAGE, $item->reference_id);
                if (! $page instanceof Page) {
                    return null;
                }
                $slug = (string) $page->slug;
                if ($slug === '') {
                    return null;
                }
                $href = self::prefixLocale($locale, CmsPublicPaths::pathForPage($page, $pageGraph));
                $label ??= (string) $page->title;

                break;

            default:
                return null;
        }

        if ($label === null || $label === '' || $href === null || $href === '') {
            return null;
        }

        return [
            'label' => $label,
            'href' => $href,
            'open_in_new_tab' => (bool) $item->open_in_new_tab,
            'children' => [],
        ];
    }

    /**
     * @param  array<string, array<int, Model>>  $references
     */
    private static function lookupReference(array $references, string $type, ?int $id): ?Model
    {
        if (! $id) {
            return null;
        }

        return $references[$type][$id] ?? null;
    }

    /**
     * Prefer per-locale translation, then primary label, then null (caller uses content/static defaults).
     */
    private static function resolvedLabel(MenuItem $item, string $locale): ?string
    {
        $item->loadMissing('translations');
        $row = $item->translations->firstWhere('locale', $locale);
        if ($row !== null && is_string($row->label) && trim($row->label) !== '') {
            return trim($row->label);
        }
        if (is_string($item->label) && trim($item->label) !== '') {
            return trim($item->label);
        }

        return null;
    }

    private static function prefixLocale(string $locale, string $path): string
    {
        $path = '/'.ltrim($path, '/');
        if ($path === '/') {
            return '/'.$locale.'/';
        }

        return '/'.$locale.$path;
    }
}
