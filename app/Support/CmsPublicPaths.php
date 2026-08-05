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
