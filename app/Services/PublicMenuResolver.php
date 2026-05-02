<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Project;
use App\Models\Service;
use App\Support\MenuStaticRoutes;
use App\Support\SiteLanguages;
use App\Support\TranslatableContentPresenter;
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

            $items = $menu->items()->orderBy('sort_order')->get();

            return self::buildResolvedTree($items, null, $locale);
        });

        return $tree;
    }

    private static function cacheKey(string $menuSlug, string $locale): string
    {
        return 'public_menu:'.$menuSlug.':'.$locale;
    }

    /**
     * @param  Collection<int, MenuItem>  $all
     * @return list<array{label: string, href: string, open_in_new_tab: bool, children: list<mixed>}>
     */
    private static function buildResolvedTree(Collection $all, ?int $parentId, string $locale): array
    {
        $out = [];
        foreach ($all->where('parent_id', $parentId)->sortBy('sort_order') as $item) {
            $resolved = self::resolveItem($item, $locale);
            if ($resolved === null) {
                continue;
            }
            $resolved['children'] = self::buildResolvedTree($all, $item->id, $locale);
            $out[] = $resolved;
        }

        return $out;
    }

    /**
     * @return array{label: string, href: string, open_in_new_tab: bool, children: array}|null
     */
    private static function resolveItem(MenuItem $item, string $locale): ?array
    {
        $label = is_string($item->label) && trim($item->label) !== '' ? trim($item->label) : null;
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

            case MenuItem::TYPE_STATIC_ROUTE:
                $key = (string) ($item->static_route_key ?? '');
                if (! MenuStaticRoutes::isValidKey($key)) {
                    return null;
                }
                $path = MenuStaticRoutes::pathsByKey()[$key] ?? '/';
                $href = self::prefixLocale($locale, $path);
                $label ??= MenuStaticRoutes::defaultLabels()[$key] ?? $key;

                break;

            case MenuItem::TYPE_PROJECT:
                $id = $item->reference_id;
                if (! $id) {
                    return null;
                }
                $project = Project::query()->select(['id', 'title', 'slug', 'content_locale'])->with('translations')->find($id);
                if ($project === null) {
                    return null;
                }
                TranslatableContentPresenter::applyProject($project, $locale);
                $slug = (string) $project->slug;
                if ($slug === '') {
                    return null;
                }
                $href = self::prefixLocale($locale, '/work/'.$slug.'/');
                $label ??= (string) $project->title;

                break;

            case MenuItem::TYPE_BLOG_POST:
                $id = $item->reference_id;
                if (! $id) {
                    return null;
                }
                $post = BlogPost::query()->select(['id', 'title', 'slug', 'content_locale'])->with('translations')->find($id);
                if ($post === null) {
                    return null;
                }
                TranslatableContentPresenter::applyBlogPost($post, $locale);
                $slug = (string) $post->slug;
                if ($slug === '') {
                    return null;
                }
                $href = self::prefixLocale($locale, '/blog/'.$slug.'/');
                $label ??= (string) $post->title;

                break;

            case MenuItem::TYPE_SERVICE:
                $id = $item->reference_id;
                if (! $id) {
                    return null;
                }
                $service = Service::query()->select(['id', 'name', 'slug', 'content_locale'])->with('translations')->find($id);
                if ($service === null) {
                    return null;
                }
                TranslatableContentPresenter::applyService($service, $locale);
                $slug = (string) $service->slug;
                if ($slug === '') {
                    return null;
                }
                $href = self::prefixLocale($locale, '/services/'.$slug.'/');
                $label ??= (string) $service->name;

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

    private static function prefixLocale(string $locale, string $path): string
    {
        $path = '/'.ltrim($path, '/');
        if ($path === '/') {
            return '/'.$locale.'/';
        }

        return '/'.$locale.$path;
    }
}
