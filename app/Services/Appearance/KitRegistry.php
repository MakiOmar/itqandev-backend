<?php

namespace App\Services\Appearance;

/**
 * Predesigned page Kits (composite section widgets).
 *
 * @phpstan-type SettingsField array{key: string, type: string, label: string, accept?: string, min?: int, max?: int, options?: list<array{value: string, label: string}>, item_fields?: list<SettingsField>, translatable?: bool}
 * @phpstan-type KitDef array{label: string, category: string, default_settings: array<string, mixed>, settings_fields: list<SettingsField>}
 */
final class KitRegistry
{
    /**
     * @return array<string, KitDef>
     */
    public static function all(): array
    {
        return array_merge(self::marketingKits(), self::contentKits(), self::chromeKits());
    }

    /**
     * Header / footer chrome kits (appearance builders).
     *
     * @return array<string, KitDef>
     */
    private static function chromeKits(): array
    {
        return [
            'header_brand' => [
                'label' => 'Header brand',
                'category' => 'Header',
                'default_settings' => [
                    'show_name' => true,
                    'show_logo' => true,
                    'transparent' => false,
                    'overlay' => false,
                ],
                'settings_fields' => [
                    ['key' => 'show_logo', 'type' => 'boolean', 'label' => 'Show logo', 'translatable' => false],
                    ['key' => 'show_name', 'type' => 'boolean', 'label' => 'Show site name', 'translatable' => false],
                    ['key' => 'transparent', 'type' => 'boolean', 'label' => 'Transparent header', 'translatable' => false],
                    ['key' => 'overlay', 'type' => 'boolean', 'label' => 'Overlay hero', 'translatable' => false],
                ],
            ],
            'header_menu' => [
                'label' => 'Header menu',
                'category' => 'Header',
                'default_settings' => [
                    'menu_slug' => 'primary',
                    'show_children_mobile' => true,
                    'layout' => 'dropdown',
                    'mega_columns' => ['mobile' => 1, 'tablet' => 2, 'desktop' => 3],
                ],
                'settings_fields' => [
                    ['key' => 'menu_slug', 'type' => 'text', 'label' => 'Menu slug', 'translatable' => false],
                    ['key' => 'show_children_mobile', 'type' => 'boolean', 'label' => 'Show nested items on mobile', 'translatable' => false],
                    [
                        'key' => 'layout',
                        'type' => 'select',
                        'label' => 'Desktop layout',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'dropdown', 'label' => 'Dropdown'],
                            ['value' => 'mega', 'label' => 'Mega menu'],
                        ],
                    ],
                    ['key' => 'mega_columns', 'type' => 'responsive_columns', 'label' => 'Mega columns', 'translatable' => false],
                ],
            ],
            'header_cta' => [
                'label' => 'Header CTA',
                'category' => 'Header',
                'default_settings' => [
                    'label' => 'Get in touch',
                    'url' => '/contact/',
                    'translations' => [
                        'ar' => ['label' => 'تواصل معنا'],
                    ],
                ],
                'settings_fields' => [
                    ['key' => 'label', 'type' => 'text', 'label' => 'Button label'],
                    ['key' => 'url', 'type' => 'url', 'label' => 'Button URL', 'translatable' => false],
                ],
            ],
            'header_actions' => [
                'label' => 'Header actions',
                'category' => 'Header',
                'default_settings' => [
                    'show_theme' => true,
                    'show_language' => true,
                    'show_auth' => true,
                ],
                'settings_fields' => [
                    ['key' => 'show_theme', 'type' => 'boolean', 'label' => 'Theme toggle', 'translatable' => false],
                    ['key' => 'show_language', 'type' => 'boolean', 'label' => 'Language switcher', 'translatable' => false],
                    ['key' => 'show_auth', 'type' => 'boolean', 'label' => 'Login / account', 'translatable' => false],
                ],
            ],
            'header_mobile_menu' => [
                'label' => 'Mobile menu',
                'category' => 'Header',
                'default_settings' => [
                    'menu_slug' => 'primary',
                    'show_below' => 'desktop',
                    'trigger' => 'icon',
                    'trigger_label' => 'Menu',
                    'panel' => 'drawer',
                    'direction' => 'end',
                    'animation' => 'slide',
                    'show_children' => true,
                    'translations' => [
                        'ar' => ['trigger_label' => 'القائمة'],
                    ],
                ],
                'settings_fields' => [
                    ['key' => 'menu_slug', 'type' => 'text', 'label' => 'Menu slug', 'translatable' => false],
                    [
                        'key' => 'show_below',
                        'type' => 'select',
                        'label' => 'Show on',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'desktop', 'label' => 'Mobile and tablet (below 1024px)'],
                            ['value' => 'tablet', 'label' => 'Mobile only (below 768px)'],
                            ['value' => 'always', 'label' => 'All screens'],
                        ],
                    ],
                    [
                        'key' => 'trigger',
                        'type' => 'select',
                        'label' => 'Button',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'icon', 'label' => 'Icon'],
                            ['value' => 'label', 'label' => 'Text'],
                            ['value' => 'icon_label', 'label' => 'Icon and text'],
                        ],
                    ],
                    ['key' => 'trigger_label', 'type' => 'text', 'label' => 'Button text'],
                    [
                        'key' => 'panel',
                        'type' => 'select',
                        'label' => 'Panel',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'drawer', 'label' => 'Drawer'],
                            ['value' => 'fullscreen', 'label' => 'Full screen'],
                        ],
                    ],
                    [
                        'key' => 'direction',
                        'type' => 'select',
                        'label' => 'Enters from',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'start', 'label' => 'Start side (left in LTR, right in RTL)'],
                            ['value' => 'end', 'label' => 'End side (right in LTR, left in RTL)'],
                            ['value' => 'top', 'label' => 'Top'],
                            ['value' => 'bottom', 'label' => 'Bottom'],
                        ],
                    ],
                    [
                        'key' => 'animation',
                        'type' => 'select',
                        'label' => 'Entry animation',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'slide', 'label' => 'Slide'],
                            ['value' => 'fade', 'label' => 'Fade'],
                            ['value' => 'none', 'label' => 'None'],
                        ],
                    ],
                    ['key' => 'show_children', 'type' => 'boolean', 'label' => 'Show nested items', 'translatable' => false],
                ],
            ],
            'header_theme_toggle' => [
                'label' => 'Theme toggle',
                'category' => 'Header',
                'default_settings' => [],
                'settings_fields' => [],
            ],
            'header_language_switcher' => [
                'label' => 'Language switcher',
                'category' => 'Header',
                'default_settings' => [
                    'show_flag' => true,
                    'show_label' => true,
                ],
                'settings_fields' => [
                    ['key' => 'show_flag', 'type' => 'boolean', 'label' => 'Show flag', 'translatable' => false],
                    ['key' => 'show_label', 'type' => 'boolean', 'label' => 'Show language name', 'translatable' => false],
                ],
            ],
            'header_account' => [
                'label' => 'Login / account',
                'category' => 'Header',
                'default_settings' => [
                    'login_label' => 'Login',
                    'login_variant' => 'outline',
                    'translations' => [
                        'ar' => ['login_label' => 'تسجيل الدخول'],
                    ],
                ],
                'settings_fields' => [
                    ['key' => 'login_label', 'type' => 'text', 'label' => 'Login button label'],
                    [
                        'key' => 'login_variant',
                        'type' => 'select',
                        'label' => 'Login button style',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'outline', 'label' => 'Outline'],
                            ['value' => 'primary', 'label' => 'Primary'],
                            ['value' => 'secondary', 'label' => 'Secondary'],
                            ['value' => 'ghost', 'label' => 'Ghost'],
                        ],
                    ],
                ],
            ],
            'header_spacer' => [
                'label' => 'Header spacer',
                'category' => 'Header',
                'default_settings' => [],
                'settings_fields' => [],
            ],
            'footer_brand' => [
                'label' => 'Footer brand',
                'category' => 'Footer',
                'default_settings' => [
                    'show_logo' => true,
                    'show_name' => true,
                    'tagline' => 'We build web, Android & iOS apps that scale.',
                    'translations' => [
                        'ar' => ['tagline' => 'نبني تطبيقات ويب وأندرويد وiOS قابلة للتوسع.'],
                    ],
                ],
                'settings_fields' => [
                    ['key' => 'show_logo', 'type' => 'boolean', 'label' => 'Show logo', 'translatable' => false],
                    ['key' => 'show_name', 'type' => 'boolean', 'label' => 'Show site name', 'translatable' => false],
                    ['key' => 'tagline', 'type' => 'textarea', 'label' => 'Tagline'],
                ],
            ],
            'footer_menu' => [
                'label' => 'Footer menu',
                'category' => 'Footer',
                'default_settings' => [
                    'title' => 'Navigate',
                    'menu_slug' => 'primary',
                    'translations' => [
                        'ar' => ['title' => 'تصفح'],
                    ],
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'menu_slug', 'type' => 'text', 'label' => 'Menu slug', 'translatable' => false],
                ],
            ],
            'footer_links' => [
                'label' => 'Footer links',
                'category' => 'Footer',
                'default_settings' => [
                    'title' => 'Quick links',
                    'links' => [
                        ['id' => 'lnk_services', 'label' => 'Services', 'url' => '/services/'],
                        ['id' => 'lnk_portfolio', 'label' => 'Portfolio', 'url' => '/portfolio/'],
                        ['id' => 'lnk_about', 'label' => 'About', 'url' => '/about/'],
                        ['id' => 'lnk_contact', 'label' => 'Contact', 'url' => '/contact/'],
                    ],
                    'translations' => [
                        'ar' => [
                            'title' => 'روابط سريعة',
                            'links' => [
                                ['id' => 'lnk_services', 'label' => 'الخدمات', 'url' => '/services/'],
                                ['id' => 'lnk_portfolio', 'label' => 'المحفظة', 'url' => '/portfolio/'],
                                ['id' => 'lnk_about', 'label' => 'من نحن', 'url' => '/about/'],
                                ['id' => 'lnk_contact', 'label' => 'تواصل', 'url' => '/contact/'],
                            ],
                        ],
                    ],
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    [
                        'key' => 'links',
                        'type' => 'repeater',
                        'label' => 'Links',
                        'item_fields' => [
                            ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                            ['key' => 'url', 'type' => 'url', 'label' => 'URL', 'translatable' => false],
                        ],
                    ],
                ],
            ],
            'footer_contact' => [
                'label' => 'Footer contact',
                'category' => 'Footer',
                'default_settings' => [
                    'title' => 'Contact',
                    'use_site_contact' => true,
                    'show_email' => true,
                    'email' => '',
                    'translations' => [
                        'ar' => ['title' => 'تواصل'],
                    ],
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'use_site_contact', 'type' => 'boolean', 'label' => 'Use site contact', 'translatable' => false],
                    ['key' => 'show_email', 'type' => 'boolean', 'label' => 'Show email', 'translatable' => false],
                    ['key' => 'email', 'type' => 'text', 'label' => 'Email override', 'translatable' => false],
                ],
            ],
            'footer_social' => [
                'label' => 'Footer social',
                'category' => 'Footer',
                'default_settings' => [
                    'title' => '',
                    'use_site_socials' => true,
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'use_site_socials', 'type' => 'boolean', 'label' => 'Use site social links', 'translatable' => false],
                ],
            ],
            'footer_rich_text' => [
                'label' => 'Footer rich text',
                'category' => 'Footer',
                'default_settings' => [
                    'title' => '',
                    'body' => '',
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'body', 'type' => 'richtext', 'label' => 'Body'],
                ],
            ],
            'footer_cta' => [
                'label' => 'Footer CTA',
                'category' => 'Footer',
                'default_settings' => [
                    'title' => 'Ready to start?',
                    'subtitle' => 'Tell us about your project.',
                    'button_label' => 'Get in touch',
                    'button_url' => '/contact/',
                    'translations' => [
                        'ar' => [
                            'title' => 'جاهز للبدء؟',
                            'subtitle' => 'أخبرنا عن مشروعك.',
                            'button_label' => 'تواصل معنا',
                        ],
                    ],
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Subtitle'],
                    ['key' => 'button_label', 'type' => 'text', 'label' => 'Button label'],
                    ['key' => 'button_url', 'type' => 'url', 'label' => 'Button URL', 'translatable' => false],
                ],
            ],
            'footer_copyright' => [
                'label' => 'Footer copyright',
                'category' => 'Footer',
                'default_settings' => [
                    'text' => '© {year} {brand}. All rights reserved.',
                    'translations' => [
                        'ar' => ['text' => '© {year} {brand}. جميع الحقوق محفوظة.'],
                    ],
                ],
                'settings_fields' => [
                    ['key' => 'text', 'type' => 'text', 'label' => 'Copyright text ({year}, {brand})'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, KitDef>
     */
    private static function marketingKits(): array
    {
        /** @var array{badge_icon: array<string, string>, tech_icons: list<array<string, mixed>>}|null $heroIcons */
        static $heroIcons = null;
        $heroIcons ??= require __DIR__.'/data/hero-default-icons.php';

        return [
            'hero' => [
                'label' => 'Hero',
                'category' => 'Marketing',
                'default_settings' => [
                    'badge_enabled' => true,
                    'badge_text' => 'Next-Gen Software Architecture',
                    'badge_icon' => $heroIcons['badge_icon'],
                    'headline' => 'We build web, Android [[& iOS]] apps that scale',
                    'subheadline' => 'From MVPs to enterprise products. Modern stack, clear process, and long-term support.',
                    'primary_cta_label' => 'Get in touch',
                    'secondary_cta_label' => 'View our portfolio',
                    'tech_enabled' => true,
                    'tech_label' => 'Tech ecosystem',
                    'tech_layout' => 'stacked',
                    'tech_divider' => true,
                    'tech_icons' => $heroIcons['tech_icons'],
                    'image' => '/hero-banner.webp',
                    'image_mobile' => '/hero-banner-mobile.webp',
                    'floating_icons_enabled' => false,
                    'floating_icons' => [],
                    'full_viewport' => false,
                    'nav_top_space' => 88,
                    'watermark_enabled' => false,
                    'watermark_text' => '',
                    'watermark_motion' => false,
                    'watermark_speed' => 40,
                    'particles_enabled' => true,
                    'particles_density' => 50,
                    'particles_speed' => 40,
                    'particles_opacity' => 55,
                    'particles_size' => 40,
                    'particles_color' => '',
                ],
                'settings_fields' => [
                    ['key' => 'badge_enabled', 'type' => 'boolean', 'label' => 'Show badge above headline', 'translatable' => false, 'group' => 'badge'],
                    ['key' => 'badge_text', 'type' => 'text', 'label' => 'Badge text', 'translatable' => true, 'group' => 'badge', 'show_if' => 'badge_enabled'],
                    ['key' => 'badge_icon', 'type' => 'icon', 'label' => 'Badge icon', 'translatable' => false, 'group' => 'badge', 'show_if' => 'badge_enabled'],
                    ['key' => 'headline', 'type' => 'text', 'label' => 'Headline (wrap words in [[ ]] to highlight)', 'group' => 'content'],
                    ['key' => 'subheadline', 'type' => 'textarea', 'label' => 'Subheadline', 'group' => 'content'],
                    ['key' => 'primary_cta_label', 'type' => 'text', 'label' => 'Primary CTA label', 'group' => 'content'],
                    ['key' => 'secondary_cta_label', 'type' => 'text', 'label' => 'Secondary CTA label', 'group' => 'content'],
                    ['key' => 'tech_enabled', 'type' => 'boolean', 'label' => 'Show tech ecosystem row', 'translatable' => false, 'group' => 'tech'],
                    ['key' => 'tech_label', 'type' => 'text', 'label' => 'Row label', 'translatable' => true, 'group' => 'tech', 'show_if' => 'tech_enabled'],
                    [
                        'key' => 'tech_layout',
                        'type' => 'select',
                        'label' => 'Label position',
                        'translatable' => false,
                        'group' => 'tech',
                        'show_if' => 'tech_enabled',
                        'options' => [
                            ['value' => 'stacked', 'label' => 'Stacked (label above icons)'],
                            ['value' => 'inline', 'label' => 'Inline (label beside icons)'],
                        ],
                    ],
                    ['key' => 'tech_divider', 'type' => 'boolean', 'label' => 'Divider line above the row', 'translatable' => false, 'group' => 'tech', 'show_if' => 'tech_enabled'],
                    [
                        'key' => 'tech_icons',
                        'type' => 'repeater',
                        'label' => 'Logos',
                        'translatable' => false,
                        'group' => 'tech',
                        'show_if' => 'tech_enabled',
                        'item_fields' => [
                            ['key' => 'icon', 'type' => 'icon', 'label' => 'Icon', 'translatable' => false],
                            ['key' => 'label', 'type' => 'text', 'label' => 'Name (tooltip)', 'translatable' => true],
                        ],
                    ],
                    ['key' => 'image', 'type' => 'media', 'label' => 'Desktop image', 'accept' => 'image/*', 'translatable' => true, 'group' => 'images'],
                    ['key' => 'image_mobile', 'type' => 'media', 'label' => 'Mobile image', 'accept' => 'image/*', 'translatable' => true, 'group' => 'images'],
                    ['key' => 'full_viewport', 'type' => 'boolean', 'label' => 'Full viewport height (100vh)', 'translatable' => false, 'group' => 'layout'],
                    ['key' => 'nav_top_space', 'type' => 'number', 'label' => 'Top space under nav (px)', 'min' => 0, 'max' => 200, 'translatable' => false, 'group' => 'layout'],
                    ['key' => 'watermark_enabled', 'type' => 'boolean', 'label' => 'Show faded text', 'translatable' => false, 'group' => 'watermark'],
                    ['key' => 'watermark_text', 'type' => 'text', 'label' => 'Text', 'translatable' => true, 'group' => 'watermark', 'show_if' => 'watermark_enabled'],
                    ['key' => 'watermark_motion', 'type' => 'boolean', 'label' => 'Scroll (follows page direction)', 'translatable' => false, 'group' => 'watermark', 'show_if' => 'watermark_enabled'],
                    ['key' => 'watermark_speed', 'type' => 'number', 'label' => 'Loop duration (seconds)', 'min' => 10, 'max' => 120, 'default' => 40, 'slider' => true, 'translatable' => false, 'group' => 'watermark', 'show_if' => 'watermark_motion'],
                    ['key' => 'particles_enabled', 'type' => 'boolean', 'label' => 'Show particles', 'translatable' => false, 'group' => 'particles'],
                    ['key' => 'particles_density', 'type' => 'number', 'label' => 'Density', 'min' => 1, 'max' => 100, 'slider' => true, 'translatable' => false, 'group' => 'particles', 'show_if' => 'particles_enabled'],
                    ['key' => 'particles_speed', 'type' => 'number', 'label' => 'Speed', 'min' => 1, 'max' => 100, 'slider' => true, 'translatable' => false, 'group' => 'particles', 'show_if' => 'particles_enabled'],
                    ['key' => 'particles_opacity', 'type' => 'number', 'label' => 'Opacity', 'min' => 1, 'max' => 100, 'slider' => true, 'translatable' => false, 'group' => 'particles', 'show_if' => 'particles_enabled'],
                    ['key' => 'particles_size', 'type' => 'number', 'label' => 'Size', 'min' => 1, 'max' => 100, 'slider' => true, 'translatable' => false, 'group' => 'particles', 'show_if' => 'particles_enabled'],
                    ['key' => 'particles_color', 'type' => 'color', 'label' => 'Color (empty = theme)', 'translatable' => false, 'group' => 'particles', 'show_if' => 'particles_enabled'],
                    ['key' => 'floating_icons_enabled', 'type' => 'boolean', 'label' => 'Show floating icons', 'translatable' => false, 'group' => 'floating_icons'],
                    ['key' => 'floating_icons', 'type' => 'floating_icons', 'label' => 'Icons', 'translatable' => false, 'group' => 'floating_icons', 'show_if' => 'floating_icons_enabled'],
                ],
            ],
            'services_teaser' => [
                'label' => 'Services teaser',
                'category' => 'Marketing',
                'default_settings' => [
                    'eyebrow' => 'Capabilities',
                    'title' => 'What we do',
                    'subtitle' => 'Full-stack development for web and mobile — from interfaces to APIs and app stores.',
                    'limit' => 6,
                ],
                'settings_fields' => [
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow'],
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Subtitle'],
                    ['key' => 'limit', 'type' => 'number', 'label' => 'Limit', 'min' => 1, 'max' => 24],
                ],
            ],
            'case_studies' => [
                'label' => 'Case studies',
                'category' => 'Marketing',
                'default_settings' => [
                    'title' => 'Selected portfolio',
                    'subtitle' => 'Recent projects we are proud of.',
                    'limit' => 6,
                    'category_ids' => [],
                    'columns' => [
                        'mobile' => 1,
                        'tablet' => 2,
                        'desktop' => 2,
                    ],
                    'card_style' => 'overlay',
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Subtitle'],
                    self::projectCardStyleField(),
                    [
                        'key' => 'category_ids',
                        'type' => 'category_multi',
                        'label' => 'Categories in filter',
                        'translatable' => false,
                    ],
                    // Empty keeps the UI-language "All" label.
                    ['key' => 'all_label', 'type' => 'text', 'label' => '"All" tab label'],
                    ['key' => 'limit', 'type' => 'number', 'label' => 'Limit per tab', 'min' => 1, 'max' => 24],
                    [
                        'key' => 'columns',
                        'type' => 'responsive_columns',
                        'label' => 'Grid columns',
                        'translatable' => false,
                    ],
                ],
            ],
            'testimonials' => [
                'label' => 'Testimonials',
                'category' => 'Marketing',
                'default_settings' => [
                    'title' => 'What our clients say',
                    'subtitle' => 'Trusted by startups and enterprises.',
                    'limit' => 6,
                    ...CarouselSettingsFields::defaults(),
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Subtitle'],
                    ['key' => 'limit', 'type' => 'number', 'label' => 'Limit', 'min' => 1, 'max' => 24],
                    ...CarouselSettingsFields::fields(),
                ],
            ],
            'tech_stack' => [
                'label' => 'Tech stack',
                'category' => 'Marketing',
                'default_settings' => [
                    'eyebrow' => 'Built with',
                ],
                'settings_fields' => [
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow'],
                ],
            ],
            'blog_preview' => [
                'label' => 'Blog preview',
                'category' => 'Marketing',
                'default_settings' => [
                    'title' => 'From the blog',
                    'subtitle' => 'Tips and updates from our team.',
                    'limit' => 3,
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Subtitle'],
                    ['key' => 'limit', 'type' => 'number', 'label' => 'Limit', 'min' => 1, 'max' => 24],
                ],
            ],
            'cta' => [
                'label' => 'Call to action',
                'category' => 'Marketing',
                'default_settings' => [
                    'title' => 'Ready to start your project?',
                    'subtitle' => "Tell us about your idea. We'll get back within 24 hours.",
                    'button_label' => 'Get in touch',
                    'button_url' => '/contact',
                    'whatsapp_enabled' => false,
                    'whatsapp_number' => '',
                    'whatsapp_label' => 'WhatsApp us',
                    'whatsapp_message' => '',
                    'translations' => [
                        'ar' => ['whatsapp_label' => 'راسلنا على واتساب'],
                    ],
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'group' => 'content'],
                    ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Subtitle', 'group' => 'content'],
                    ['key' => 'button_label', 'type' => 'text', 'label' => 'Button label', 'group' => 'content'],
                    ['key' => 'button_url', 'type' => 'url', 'label' => 'Button URL', 'translatable' => false, 'group' => 'content'],
                    ['key' => 'whatsapp_enabled', 'type' => 'boolean', 'label' => 'Show WhatsApp', 'translatable' => false, 'group' => 'whatsapp'],
                    ['key' => 'whatsapp_number', 'type' => 'text', 'label' => 'WhatsApp number (international, digits only, e.g. 9665XXXXXXXX)', 'translatable' => false, 'group' => 'whatsapp', 'show_if' => 'whatsapp_enabled'],
                    ['key' => 'whatsapp_label', 'type' => 'text', 'label' => 'WhatsApp button label', 'translatable' => true, 'group' => 'whatsapp', 'show_if' => 'whatsapp_enabled'],
                    ['key' => 'whatsapp_message', 'type' => 'textarea', 'label' => 'Pre-filled message', 'translatable' => true, 'group' => 'whatsapp', 'show_if' => 'whatsapp_enabled'],
                ],
            ],
            'form' => [
                'label' => 'Form',
                'category' => 'Marketing',
                'default_settings' => [
                    'form_slug' => '',
                    'title' => '',
                    'subtitle' => '',
                ],
                'settings_fields' => [
                    ['key' => 'form_slug', 'type' => 'form', 'label' => 'Form', 'translatable' => false],
                    ['key' => 'title', 'type' => 'text', 'label' => 'Optional heading'],
                    ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Optional intro'],
                ],
            ],
            'projects_list' => [
                'label' => 'Portfolio / projects list',
                'category' => 'Marketing',
                'default_settings' => [
                    'show_filters' => true,
                    'category_ids' => [],
                    'card_style' => 'overlay',
                ],
                'settings_fields' => [
                    self::projectCardStyleField(),
                    [
                        'key' => 'show_filters',
                        'type' => 'boolean',
                        'label' => 'Show category side filters',
                        'translatable' => false,
                    ],
                    [
                        'key' => 'category_ids',
                        'type' => 'category_multi',
                        'label' => 'Categories in filter',
                        'translatable' => false,
                    ],
                ],
            ],
            'blog_posts_list' => [
                'label' => 'Blog / articles list',
                'category' => 'Marketing',
                'default_settings' => [
                    'per_page' => 12,
                    'columns' => ['mobile' => 1, 'tablet' => 2, 'desktop' => 3],
                ],
                'settings_fields' => [
                    [
                        'key' => 'per_page',
                        'type' => 'number',
                        'label' => 'Posts per page',
                        'min' => 1,
                        'max' => 48,
                        'translatable' => false,
                    ],
                    ['key' => 'columns', 'type' => 'responsive_columns', 'label' => 'Columns', 'translatable' => false],
                ],
            ],
        ];
    }

    /**
     * @return array<string, KitDef>
     */
    private static function contentKits(): array
    {
        return [
            'faq' => [
                'label' => 'FAQ',
                'category' => 'Content',
                'default_settings' => [
                    'title' => 'Frequently asked questions',
                    'items' => [
                        ['question' => "What's included?", 'answer' => 'Scope is confirmed in a short discovery call.'],
                        ['question' => 'Do you do retainers?', 'answer' => 'Yes — ask for a custom quote.'],
                    ],
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    [
                        'key' => 'items',
                        'type' => 'repeater',
                        'label' => 'Questions',
                        'translatable' => true,
                        'item_fields' => [
                            ['key' => 'question', 'type' => 'text', 'label' => 'Question'],
                            ['key' => 'answer', 'type' => 'textarea', 'label' => 'Answer'],
                        ],
                    ],
                ],
            ],
            'stats' => [
                'label' => 'Stats / counters',
                'category' => 'Content',
                'default_settings' => [
                    'title' => '',
                    'items' => [
                        ['value' => 50, 'label' => 'Projects delivered'],
                        ['value' => 10, 'label' => 'Years experience'],
                        ['value' => 100, 'label' => 'Happy clients'],
                    ],
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Optional title'],
                    [
                        'key' => 'items',
                        'type' => 'repeater',
                        'label' => 'Stats',
                        'translatable' => true,
                        'item_fields' => [
                            ['key' => 'value', 'type' => 'number', 'label' => 'Value', 'min' => 0, 'max' => 999999, 'translatable' => false],
                            ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                        ],
                    ],
                ],
            ],
            'pricing' => [
                'label' => 'Pricing',
                'category' => 'Content',
                'default_settings' => [
                    'title' => 'Pricing',
                    'subtitle' => 'Transparent packages. Custom quotes for larger scope.',
                    'tiers' => [
                        [
                            'name' => 'Starter',
                            'price' => '$2,500',
                            'period' => 'project',
                            'description' => 'Small sites and MVPs.',
                            'features' => "Discovery workshop\nResponsive build\n2 revision rounds",
                            'cta' => 'Get started',
                            'highlighted' => false,
                        ],
                        [
                            'name' => 'Growth',
                            'price' => '$7,500',
                            'period' => 'project',
                            'description' => 'Full product surface.',
                            'features' => "UX + UI\nIntegrations\nPerformance pass\nLaunch support",
                            'cta' => 'Talk to us',
                            'highlighted' => true,
                        ],
                    ],
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Subtitle'],
                    [
                        'key' => 'tiers',
                        'type' => 'repeater',
                        'label' => 'Tiers',
                        'translatable' => true,
                        'item_fields' => [
                            ['key' => 'name', 'type' => 'text', 'label' => 'Name'],
                            ['key' => 'price', 'type' => 'text', 'label' => 'Price'],
                            ['key' => 'period', 'type' => 'text', 'label' => 'Period'],
                            ['key' => 'description', 'type' => 'textarea', 'label' => 'Description'],
                            ['key' => 'features', 'type' => 'textarea', 'label' => 'Features (one per line)'],
                            ['key' => 'cta', 'type' => 'text', 'label' => 'CTA label'],
                            ['key' => 'highlighted', 'type' => 'boolean', 'label' => 'Highlighted', 'translatable' => false],
                        ],
                    ],
                ],
            ],
            'contact_info' => [
                'label' => 'Contact info',
                'category' => 'Content',
                'default_settings' => [
                    'office_heading' => 'Office',
                    'address' => '',
                    'email' => '',
                    'phone' => '',
                    'calendar_link' => '',
                    'calendar_label' => 'Book a call',
                    'use_site_contact' => true,
                    'socials' => [],
                ],
                'settings_fields' => [
                    [
                        'key' => 'use_site_contact',
                        'type' => 'boolean',
                        'label' => 'Fill empty fields from site contact settings',
                        'translatable' => false,
                    ],
                    ['key' => 'office_heading', 'type' => 'text', 'label' => 'Heading'],
                    ['key' => 'address', 'type' => 'textarea', 'label' => 'Address'],
                    ['key' => 'email', 'type' => 'text', 'label' => 'Email'],
                    ['key' => 'phone', 'type' => 'text', 'label' => 'Phone'],
                    ['key' => 'calendar_link', 'type' => 'url', 'label' => 'Calendar URL', 'translatable' => false],
                    ['key' => 'calendar_label', 'type' => 'text', 'label' => 'Calendar button label'],
                    [
                        'key' => 'socials',
                        'type' => 'repeater',
                        'label' => 'Social links',
                        'translatable' => false,
                        'item_fields' => [
                            ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                            ['key' => 'url', 'type' => 'url', 'label' => 'URL'],
                        ],
                    ],
                ],
            ],
            'image_text' => [
                'label' => 'Image + text',
                'category' => 'Content',
                // Presentation (object-fit, radius, hover, …) lives on sibling `styles`, not settings.
                'default_settings' => [
                    'eyebrow' => '',
                    'title' => 'A compelling headline',
                    'body' => 'Supporting copy goes here.',
                    'image' => null,
                    'image_position' => 'right',
                    'button_label' => '',
                    'button_url' => '',
                ],
                'settings_fields' => [
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow'],
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'body', 'type' => 'richtext', 'label' => 'Body'],
                    ['key' => 'image', 'type' => 'media', 'label' => 'Image', 'accept' => 'image/*', 'translatable' => true],
                    [
                        'key' => 'image_position',
                        'type' => 'select',
                        'label' => 'Image position',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'left', 'label' => 'Left'],
                            ['value' => 'right', 'label' => 'Right'],
                        ],
                    ],
                    ['key' => 'button_label', 'type' => 'text', 'label' => 'Button label'],
                    ['key' => 'button_url', 'type' => 'url', 'label' => 'Button URL', 'translatable' => false],
                ],
            ],
            'timeline' => [
                'label' => 'Timeline / process',
                'category' => 'Content',
                'default_settings' => [
                    'title' => 'How we work',
                    'subtitle' => '',
                    'items' => [
                        ['year' => '', 'title' => 'Discover', 'description' => 'Goals, constraints, and success metrics.'],
                        ['year' => '', 'title' => 'Design', 'description' => 'UX and UI aligned to your brand.'],
                        ['year' => '', 'title' => 'Build', 'description' => 'Iterative delivery with clear milestones.'],
                        ['year' => '', 'title' => 'Launch', 'description' => 'Ship, measure, and improve.'],
                    ],
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Optional subtitle'],
                    [
                        'key' => 'items',
                        'type' => 'repeater',
                        'label' => 'Steps',
                        'translatable' => true,
                        'item_fields' => [
                            ['key' => 'year', 'type' => 'text', 'label' => 'Year / step marker'],
                            ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                            ['key' => 'description', 'type' => 'textarea', 'label' => 'Description'],
                        ],
                    ],
                ],
            ],
            'team' => [
                'label' => 'Team',
                'category' => 'Content',
                'default_settings' => [
                    'title' => 'The team',
                    'members' => [
                        ['name' => 'Alex Rivera', 'role' => 'Founder', 'bio' => '', 'avatar' => null],
                    ],
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    [
                        'key' => 'members',
                        'type' => 'repeater',
                        'label' => 'Members',
                        'translatable' => true,
                        'item_fields' => [
                            ['key' => 'name', 'type' => 'text', 'label' => 'Name'],
                            ['key' => 'role', 'type' => 'text', 'label' => 'Role'],
                            ['key' => 'bio', 'type' => 'textarea', 'label' => 'Bio'],
                            ['key' => 'avatar', 'type' => 'media', 'label' => 'Avatar', 'accept' => 'image/*', 'translatable' => false],
                        ],
                    ],
                    ['key' => 'columns', 'type' => 'responsive_columns', 'label' => 'Columns', 'translatable' => false],
                ],
            ],
            'feature_grid' => [
                'label' => 'Feature / values grid',
                'category' => 'Content',
                'default_settings' => [
                    'title' => 'Our values',
                    'items' => [
                        ['title' => 'Quality', 'description' => 'Crafted with care.'],
                        ['title' => 'Transparency', 'description' => 'Clear communication.'],
                        ['title' => 'Partnership', 'description' => 'Long-term support.'],
                    ],
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    [
                        'key' => 'items',
                        'type' => 'repeater',
                        'label' => 'Items',
                        'translatable' => true,
                        'item_fields' => [
                            ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                            ['key' => 'description', 'type' => 'textarea', 'label' => 'Description'],
                        ],
                    ],
                    ['key' => 'columns', 'type' => 'responsive_columns', 'label' => 'Columns', 'translatable' => false],
                ],
            ],
            'logo_cloud' => [
                'label' => 'Logo cloud',
                'category' => 'Trust',
                'default_settings' => [
                    'title' => 'Trusted by',
                    'logos' => [],
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    [
                        'key' => 'logos',
                        'type' => 'repeater',
                        'label' => 'Logos',
                        'translatable' => false,
                        'item_fields' => [
                            ['key' => 'image', 'type' => 'media', 'label' => 'Logo', 'accept' => 'image/*'],
                            ['key' => 'alt', 'type' => 'text', 'label' => 'Alt text'],
                            ['key' => 'url', 'type' => 'url', 'label' => 'Optional link'],
                        ],
                    ],
                    ['key' => 'columns', 'type' => 'responsive_columns', 'label' => 'Columns', 'translatable' => false],
                ],
            ],
            'accordion_content' => [
                'label' => 'Accordion',
                'category' => 'Engagement',
                'default_settings' => [
                    'title' => '',
                    'items' => [
                        ['title' => 'Section one', 'body' => 'Details…'],
                    ],
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Optional title'],
                    [
                        'key' => 'items',
                        'type' => 'repeater',
                        'label' => 'Panels',
                        'translatable' => true,
                        'item_fields' => [
                            ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                            ['key' => 'body', 'type' => 'textarea', 'label' => 'Body'],
                        ],
                    ],
                ],
            ],
            'tabs_content' => [
                'label' => 'Tabs',
                'category' => 'Engagement',
                'default_settings' => [
                    'items' => [
                        ['title' => 'Tab one', 'body' => 'Content…'],
                        ['title' => 'Tab two', 'body' => 'More…'],
                    ],
                ],
                'settings_fields' => [
                    [
                        'key' => 'items',
                        'type' => 'repeater',
                        'label' => 'Tabs',
                        'translatable' => true,
                        'item_fields' => [
                            ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                            ['key' => 'body', 'type' => 'textarea', 'label' => 'Body'],
                        ],
                    ],
                ],
            ],
            'video_cta' => [
                'label' => 'Video + CTA',
                'category' => 'Engagement',
                'default_settings' => [
                    'title' => 'See it in action',
                    'subtitle' => '',
                    'video_url' => '',
                    'button_label' => 'Get started',
                    'button_url' => '/contact',
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Subtitle'],
                    ['key' => 'video_url', 'type' => 'video', 'label' => 'Video URL', 'translatable' => false],
                    ['key' => 'button_label', 'type' => 'text', 'label' => 'Button label'],
                    ['key' => 'button_url', 'type' => 'url', 'label' => 'Button URL', 'translatable' => false],
                ],
            ],
            'page_header' => [
                'label' => 'Page title + breadcrumbs',
                'category' => 'Navigation',
                'default_settings' => [
                    'show_breadcrumbs' => true,
                    'show_title' => true,
                    'home_label' => 'Home',
                    'eyebrow' => '',
                    'title_override' => '',
                    'subtitle' => '',
                    'extra_crumbs' => [],
                ],
                'settings_fields' => [
                    ['key' => 'show_breadcrumbs', 'type' => 'boolean', 'label' => 'Show breadcrumbs', 'translatable' => false],
                    ['key' => 'show_title', 'type' => 'boolean', 'label' => 'Show page title', 'translatable' => false],
                    ['key' => 'home_label', 'type' => 'text', 'label' => 'Home crumb label'],
                    ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow (optional)'],
                    ['key' => 'title_override', 'type' => 'text', 'label' => 'Title override (empty = current page title)'],
                    ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Optional subtitle'],
                    [
                        'key' => 'extra_crumbs',
                        'type' => 'repeater',
                        'label' => 'Extra crumbs before current page',
                        'translatable' => true,
                        'item_fields' => [
                            ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                            ['key' => 'url', 'type' => 'url', 'label' => 'URL', 'translatable' => false],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Project card design shared by the case studies and projects list kits;
     * values must match `CaseStudyCard` / `CaseStudyDetailedCard` on the website.
     *
     * @return SettingsField
     */
    private static function projectCardStyleField(): array
    {
        return [
            'key' => 'card_style',
            'type' => 'select',
            'label' => 'Card style',
            'translatable' => false,
            'options' => [
                ['value' => 'overlay', 'label' => 'Overlay'],
                ['value' => 'detailed', 'label' => 'Detailed'],
            ],
        ];
    }

    public static function has(string $type): bool
    {
        return array_key_exists($type, self::all());
    }

    /**
     * @return list<array{type: string, kind: string, label: string, category: string, default_settings: array<string, mixed>, settings_fields: list<SettingsField>}>
     */
    public static function forAdmin(): array
    {
        $out = [];
        foreach (self::all() as $type => $def) {
            $out[] = [
                'type' => $type,
                'kind' => PageLeafRegistry::KIND_KIT,
                'label' => $def['label'],
                'category' => $def['category'],
                'default_settings' => $def['default_settings'],
                'settings_fields' => self::fieldsForAdmin($def['settings_fields']),
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function translatableKeys(string $type): array
    {
        $all = self::all();
        $fields = is_array($all[$type]['settings_fields'] ?? null) ? $all[$type]['settings_fields'] : [];

        return AppearanceLocalizedSettings::translatableKeysFromFields($fields);
    }

    /**
     * @param  list<SettingsField>  $fields
     * @return list<SettingsField>
     */
    private static function fieldsForAdmin(array $fields): array
    {
        $out = [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            $field['translatable'] = AppearanceLocalizedSettings::isFieldTranslatable($field);
            if (isset($field['item_fields']) && is_array($field['item_fields'])) {
                $field['item_fields'] = self::fieldsForAdmin($field['item_fields']);
            }
            $out[] = $field;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultSettings(string $type): array
    {
        $all = self::all();

        return $all[$type]['default_settings'] ?? [];
    }
}
