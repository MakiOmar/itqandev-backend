<?php

namespace App\Support;

/**
 * Built-in marketing paths (no DB record). Keys align with admin + public menu UIs.
 */
final class MenuStaticRoutes
{
    /** @var list<string> */
    public const KEYS = [
        'home',
        'services',
        'work',
        'about',
        'pricing',
        'blog',
        'contact',
    ];

    /**
     * Path segment after UI locale (leading slash, trailing slash for Qwik City).
     *
     * @return array<string, string>
     */
    public static function pathsByKey(): array
    {
        return [
            'home' => '/',
            'services' => '/services/',
            'work' => '/work/',
            'about' => '/about/',
            'pricing' => '/pricing/',
            'blog' => '/blog/',
            'contact' => '/contact/',
        ];
    }

    /**
     * Default English labels when the menu item has no custom label.
     *
     * @return array<string, string>
     */
    public static function defaultLabels(): array
    {
        return [
            'home' => 'Home',
            'services' => 'Services',
            'work' => 'Work',
            'about' => 'About',
            'pricing' => 'Pricing',
            'blog' => 'Blog',
            'contact' => 'Contact',
        ];
    }

    public static function isValidKey(?string $key): bool
    {
        return is_string($key) && in_array($key, self::KEYS, true);
    }
}
