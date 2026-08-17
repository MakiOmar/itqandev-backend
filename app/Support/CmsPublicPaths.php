<?php

namespace App\Support;

/**
 * Pretty public paths for known CMS page slugs (menus + marketing routes).
 * Unknown slugs use `/pages/{slug}/`.
 */
final class CmsPublicPaths
{
    /**
     * Path after UI locale (leading slash; trailing slash for Qwik City).
     *
     * @return array<string, string>
     */
    public static function prettyPathsBySlug(): array
    {
        return [
            'services' => '/services/',
            'portfolio' => '/portfolio/',
            'about' => '/about/',
            'pricing' => '/pricing/',
            'articles' => '/blog/',
            'contact' => '/contact/',
        ];
    }

    public static function pathForPageSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            return '/pages/';
        }

        $map = self::prettyPathsBySlug();

        return $map[$slug] ?? '/pages/'.$slug.'/';
    }

    /**
     * Nested children always use `/pages/{parent}/{child}/`.
     * Top-level pages keep pretty marketing routes when the slug is known.
     *
     * @param  array<int, \App\Models\Page>  $byId
     */
    public static function pathForPage(\App\Models\Page $page, array $byId = []): string
    {
        if ($page->parent_id) {
            $nested = PageHierarchy::pathFor($page, $byId);
            if ($nested === '') {
                return '/pages/';
            }

            return '/pages/'.$nested.'/';
        }

        return self::pathForPageSlug((string) $page->slug);
    }

    /**
     * Map legacy menu static_route_key → CMS page slug (null = home → custom link `/`).
     *
     * @return array<string, string|null>
     */
    public static function legacyStaticKeyToPageSlug(): array
    {
        return [
            'home' => null,
            'services' => 'services',
            'portfolio' => 'portfolio',
            'work' => 'portfolio',
            'about' => 'about',
            'pricing' => 'pricing',
            'blog' => 'articles',
            'contact' => 'contact',
        ];
    }
}
