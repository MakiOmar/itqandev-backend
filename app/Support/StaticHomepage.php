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
     */
    public static function page(?array $settings = null): ?Page
    {
        if (! FeatureModules::enabled('pages') || ! Schema::hasTable('pages')) {
            return null;
        }
        if (self::showOnFront($settings) !== self::SHOW_PAGE) {
            return null;
        }
        $id = self::pageOnFrontId($settings);
        if ($id === null) {
            return null;
        }

        $page = Page::query()
            ->whereKey($id)
            ->where('status', Page::STATUS_PUBLISHED)
            ->first();

        return $page instanceof Page ? $page : null;
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
