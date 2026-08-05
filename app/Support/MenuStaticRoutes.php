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
        'portfolio',
        'about',
        'pricing',
        'blog',
        'contact',
        /** @deprecated Prefer `portfolio`; still accepted for older menu rows. */
        'work',
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
            'portfolio' => '/portfolio/',
            'work' => '/portfolio/',
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
            'portfolio' => 'Portfolio',
            'work' => 'Portfolio',
            'about' => 'About',
            'pricing' => 'Pricing',
            'blog' => 'Blog',
            'contact' => 'Contact',
        ];
    }

    /**
     * Locale-aware default labels for static routes (when item label/translation empty).
     *
     * @return array<string, string>
     */
    public static function defaultLabelsForLocale(string $locale): array
    {
        $locale = strtolower(trim($locale));
        if ($locale === 'ar') {
            return [
                'home' => 'الرئيسية',
                'services' => 'الخدمات',
                'portfolio' => 'المحفظة',
                'work' => 'المحفظة',
                'about' => 'من نحن',
                'pricing' => 'الأسعار',
                'blog' => 'المدونة',
                'contact' => 'تواصل',
            ];
        }

        return self::defaultLabels();
    }

    public static function isValidKey(?string $key): bool
    {
        return is_string($key) && in_array($key, self::KEYS, true);
    }
}
