<?php

namespace App\Support;

use App\Models\Page;
use Illuminate\Support\Facades\Schema;

/**
 * WordPress-style static front page: show Appearance homepage, or a published CMS page at `/`.
 */
final class StaticHomepage
{
    public const SHOW_BUILDER = 'builder';

    public const SHOW_PAGE = 'page';

    public static function showOnFront(?array $settings = null): string
    {
        $raw = $settings ?? ProjectSettingsStore::load();
        $value = strtolower(trim((string) ($raw['show_on_front'] ?? self::SHOW_BUILDER)));

        return $value === self::SHOW_PAGE ? self::SHOW_PAGE : self::SHOW_BUILDER;
    }

    public static function pageOnFrontId(?array $settings = null): ?int
    {
        $raw = $settings ?? ProjectSettingsStore::load();
        $id = (int) ($raw['page_on_front'] ?? 0);

        return $id > 0 ? $id : null;
    }

    public static function normalizeShowOnFront(mixed $value): string
    {
        $v = strtolower(trim((string) $value));

        return $v === self::SHOW_PAGE ? self::SHOW_PAGE : self::SHOW_BUILDER;
    }

    public static function normalizePageOnFrontId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    /**
     * Published CMS page used as `/`, or null when Appearance homepage should render.
     *
     * A published `page_on_front` wins even if `show_on_front` was left as `builder`
     * (operators often assign the home CMS page in Page Builder without flipping the flag).
     * Intentional Appearance-homepage sites keep `page_on_front` null.
     *
     * Memoized per request (only `id` and `slug` are loaded): path helpers call this once per listed page.
     */
    public static function page(?array $settings = null): ?Page
    {
        if (! FeatureModules::enabled('pages')) {
            return null;
        }
        $id = self::pageOnFrontId($settings);
        if ($id === null) {
            return null;
        }

        return app(RequestMemo::class)->remember(self::memoKey($id), function () use ($id): ?Page {
            if (! Schema::hasTable('pages')) {
                return null;
            }

            return Page::query()
                ->select(['id', 'slug'])
                ->whereKey($id)
                ->where('status', Page::STATUS_PUBLISHED)
                ->first();
        });
    }

    /**
     * Drop the request copy after the page row changes (publish/unpublish/slug edit).
     */
    public static function forgetPage(int $id): void
    {
        app(RequestMemo::class)->forget(self::memoKey($id));
    }

    private static function memoKey(int $id): string
    {
        return 'static-homepage:page:'.$id;
    }

    public static function isFrontPageId(?int $id): bool
    {
        if ($id === null || $id <= 0) {
            return false;
        }
        $front = self::page();

        return $front !== null && (int) $front->id === $id;
    }

    public static function isFrontPageSlug(?string $slug): bool
    {
        $slug = strtolower(trim((string) $slug));
        if ($slug === '') {
            return false;
        }
        $front = self::page();

        return $front !== null && strtolower(trim((string) $front->slug)) === $slug;
    }

    /**
     * Compact payload for public site-meta / shell.
     *
     * @return array{show_on_front: string, page_on_front: int|null, slug: string|null}
     */
    public static function publicMeta(?array $settings = null): array
    {
        $page = self::page($settings);

        return [
            'show_on_front' => $page !== null ? self::SHOW_PAGE : self::SHOW_BUILDER,
            'page_on_front' => $page !== null ? (int) $page->id : self::pageOnFrontId($settings),
            'slug' => $page !== null ? (string) $page->slug : null,
        ];
    }
}
