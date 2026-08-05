<?php

namespace App\Services\Appearance;

/**
 * Predesigned page Kits (composite section widgets).
 *
 * @phpstan-type SettingsField array{key: string, type: string, label: string, accept?: string, min?: int, max?: int, options?: list<array{value: string, label: string}>, item_fields?: list<SettingsField>, translatable?: bool}
 * @phpstan-type KitDef array{label: string, category: string, max_instances: int|null, default_settings: array<string, mixed>, settings_fields: list<SettingsField>}
 */
final class KitRegistry
{
    /**
     * @return array<string, KitDef>
     */
    public static function all(): array
    {
        return array_merge(self::marketingKits(), self::contentKits());
    }

    /**
     * @return array<string, KitDef>
     */
    private static function marketingKits(): array
    {
        return [
            'hero' => [
                'label' => 'Hero',
                'category' => 'Marketing',
                'max_instances' => 1,
                'default_settings' => [
                    'headline' => 'We build web, Android & iOS apps that scale',
                    'subheadline' => 'From MVPs to enterprise products. Modern stack, clear process, and long-term support.',
                    'primary_cta_label' => 'Get in touch',
                    'secondary_cta_label' => 'View our work',
                    'image' => '/hero-banner.webp',
                    'image_mobile' => '/hero-banner-mobile.webp',
                    'floating_icons_enabled' => false,
                    'floating_icons' => [],
                    'full_viewport' => false,
                    'nav_top_space' => 88,
                    'watermark_enabled' => false,
                    'watermark_text' => '',
                    'particles_enabled' => true,
                    'particles_density' => 50,
                    'particles_speed' => 40,
                    'particles_opacity' => 55,
                    'particles_size' => 40,
                    'particles_color' => '',
                ],
                'settings_fields' => [
                    ['key' => 'headline', 'type' => 'text', 'label' => 'Headline'],
                    ['key' => 'subheadline', 'type' => 'textarea', 'label' => 'Subheadline'],
                    ['key' => 'primary_cta_label', 'type' => 'text', 'label' => 'Primary CTA label'],
                    ['key' => 'secondary_cta_label', 'type' => 'text', 'label' => 'Secondary CTA label'],
                    ['key' => 'image', 'type' => 'media', 'label' => 'Desktop image', 'accept' => 'image/*', 'translatable' => true],
                    ['key' => 'image_mobile', 'type' => 'media', 'label' => 'Mobile image', 'accept' => 'image/*', 'translatable' => true],
                    ['key' => 'full_viewport', 'type' => 'boolean', 'label' => 'Full viewport height (100vh)', 'translatable' => false],
                    ['key' => 'nav_top_space', 'type' => 'number', 'label' => 'Top space under nav (px)', 'min' => 0, 'max' => 200, 'translatable' => false],
                    ['key' => 'watermark_enabled', 'type' => 'boolean', 'label' => 'Faded background text', 'translatable' => false],
                    ['key' => 'watermark_text', 'type' => 'text', 'label' => 'Watermark text', 'translatable' => true],
                    ['key' => 'particles_enabled', 'type' => 'boolean', 'label' => 'Particles behind hero', 'translatable' => false],
                    ['key' => 'particles_density', 'type' => 'number', 'label' => 'Particles density', 'min' => 1, 'max' => 100, 'translatable' => false],
                    ['key' => 'particles_speed', 'type' => 'number', 'label' => 'Particles speed', 'min' => 1, 'max' => 100, 'translatable' => false],
                    ['key' => 'particles_opacity', 'type' => 'number', 'label' => 'Particles opacity', 'min' => 1, 'max' => 100, 'translatable' => false],
                    ['key' => 'particles_size', 'type' => 'number', 'label' => 'Particles size', 'min' => 1, 'max' => 100, 'translatable' => false],
                    ['key' => 'particles_color', 'type' => 'color', 'label' => 'Particles color (empty = theme)', 'translatable' => false],
                    ['key' => 'floating_icons_enabled', 'type' => 'boolean', 'label' => 'Floating icons around image', 'translatable' => false],
                    ['key' => 'floating_icons', 'type' => 'floating_icons', 'label' => 'Floating icons', 'translatable' => false],
                ],
            ],
            'services_teaser' => [
                'label' => 'Services teaser',
                'category' => 'Marketing',
                'max_instances' => 1,
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
                'max_instances' => 1,
                'default_settings' => [
                    'title' => 'Selected work',
                    'subtitle' => 'Recent projects we are proud of.',
                    'limit' => 3,
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Subtitle'],
                    ['key' => 'limit', 'type' => 'number', 'label' => 'Limit', 'min' => 1, 'max' => 24],
                ],
            ],
            'testimonials' => [
                'label' => 'Testimonials',
                'category' => 'Marketing',
                'max_instances' => 1,
                'default_settings' => [
                    'title' => 'What our clients say',
                    'subtitle' => 'Trusted by startups and enterprises.',
                    'limit' => 6,
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Subtitle'],
                    ['key' => 'limit', 'type' => 'number', 'label' => 'Limit', 'min' => 1, 'max' => 24],
                ],
            ],
            'tech_stack' => [
                'label' => 'Tech stack',
                'category' => 'Marketing',
                'max_instances' => 1,
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
                'max_instances' => 1,
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
                'max_instances' => 1,
                'default_settings' => [
                    'title' => 'Ready to start your project?',
                    'subtitle' => "Tell us about your idea. We'll get back within 24 hours.",
                    'button_label' => 'Get in touch',
                    'button_url' => '/contact',
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Subtitle'],
                    ['key' => 'button_label', 'type' => 'text', 'label' => 'Button label'],
                    ['key' => 'button_url', 'type' => 'url', 'label' => 'Button URL', 'translatable' => false],
                ],
            ],
            'form' => [
                'label' => 'Form',
                'category' => 'Marketing',
                'max_instances' => null,
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
                'max_instances' => 1,
                'default_settings' => [
                    'show_filters' => true,
                ],
                'settings_fields' => [
                    [
                        'key' => 'show_filters',
                        'type' => 'boolean',
                        'label' => 'Show category side filters',
                        'translatable' => false,
                    ],
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
                'max_instances' => null,
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
                'max_instances' => null,
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
                'max_instances' => 1,
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
                'max_instances' => null,
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
                'max_instances' => null,
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
                'max_instances' => null,
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
                'max_instances' => null,
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
                ],
            ],
            'feature_grid' => [
                'label' => 'Feature / values grid',
                'category' => 'Content',
                'max_instances' => null,
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
                ],
            ],
            'logo_cloud' => [
                'label' => 'Logo cloud',
                'category' => 'Trust',
                'max_instances' => null,
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
                ],
            ],
            'accordion_content' => [
                'label' => 'Accordion',
                'category' => 'Engagement',
                'max_instances' => null,
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
                'max_instances' => null,
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
                'max_instances' => null,
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
                'max_instances' => 1,
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

    public static function has(string $type): bool
    {
        return array_key_exists($type, self::all());
    }

    /**
     * @return list<array{type: string, kind: string, label: string, category: string, max_instances: int|null, default_settings: array<string, mixed>, settings_fields: list<SettingsField>}>
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
                'max_instances' => $def['max_instances'],
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

    public static function maxInstances(string $type): ?int
    {
        $all = self::all();

        return $all[$type]['max_instances'] ?? null;
    }
}
