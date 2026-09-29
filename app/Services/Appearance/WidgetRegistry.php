<?php

namespace App\Services\Appearance;

/**
 * Atomic page Widgets for the page builder.
 *
 * @phpstan-type SettingsField array{key: string, type: string, label: string, accept?: string, min?: int, max?: int, options?: list<array{value: string, label: string}>, item_fields?: list<SettingsField>, translatable?: bool}
 * @phpstan-type WidgetDef array{label: string, category: string, max_instances: int|null, default_settings: array<string, mixed>, settings_fields: list<SettingsField>}
 */
final class WidgetRegistry
{
    /**
     * @return array<string, WidgetDef>
     */
    public static function all(): array
    {
        return array_merge(
            self::typography(),
            self::media(),
            self::actions(),
            self::layout(),
            self::misc(),
            self::theme(),
            self::extras(),
            self::content(),
        );
    }

    /**
     * Widgets fed by module data (rendered from the page's marketing support payload, not settings).
     *
     * @return array<string, WidgetDef>
     */
    private static function content(): array
    {
        $bool = static fn (string $key, string $label): array => [
            'key' => $key, 'type' => 'boolean', 'label' => $label, 'translatable' => false,
        ];

        return [
            'testimonial_list' => [
                'label' => 'Testimonials',
                'category' => 'Content',
                'max_instances' => null,
                'default_settings' => [
                    'title' => '',
                    'subtitle' => '',
                    'layout' => 'grid',
                    'limit' => 6,
                    'columns' => ['mobile' => 1, 'tablet' => 2, 'desktop' => 3],
                    'card_style' => 'card',
                    'show_rating' => true,
                    'show_avatar' => true,
                    'show_role' => true,
                    'show_project' => true,
                    'autoplay' => false,
                    'autoplay_seconds' => 6,
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'translatable' => true],
                    ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Subtitle', 'translatable' => true],
                    [
                        'key' => 'layout',
                        'type' => 'select',
                        'label' => 'Layout',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'grid', 'label' => 'Grid'],
                            ['value' => 'carousel', 'label' => 'Carousel'],
                        ],
                    ],
                    ['key' => 'limit', 'type' => 'number', 'label' => 'Number of testimonials', 'min' => 1, 'max' => 24, 'translatable' => false],
                    ['key' => 'columns', 'type' => 'responsive_columns', 'label' => 'Columns', 'translatable' => false],
                    [
                        'key' => 'card_style',
                        'type' => 'select',
                        'label' => 'Card style',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'card', 'label' => 'Card'],
                            ['value' => 'minimal', 'label' => 'Minimal'],
                        ],
                    ],
                    $bool('show_rating', 'Show rating'),
                    $bool('show_avatar', 'Show avatar'),
                    $bool('show_role', 'Show role / company'),
                    $bool('show_project', 'Show project'),
                    $bool('autoplay', 'Autoplay (carousel)'),
                    ['key' => 'autoplay_seconds', 'type' => 'number', 'label' => 'Autoplay interval (seconds)', 'min' => 3, 'max' => 15, 'translatable' => false],
                ],
            ],
        ];
    }

    /**
     * @return array<string, WidgetDef>
     */
    private static function typography(): array
    {
        return [
            'heading' => [
                'label' => 'Heading',
                'category' => 'Typography',
                'max_instances' => null,
                'default_settings' => [
                    'text' => 'Heading',
                    'level' => 'h2',
                    'align' => 'start',
                ],
                'settings_fields' => [
                    ['key' => 'text', 'type' => 'text', 'label' => 'Text'],
                    [
                        'key' => 'level',
                        'type' => 'select',
                        'label' => 'Level',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'h1', 'label' => 'H1'],
                            ['value' => 'h2', 'label' => 'H2'],
                            ['value' => 'h3', 'label' => 'H3'],
                            ['value' => 'h4', 'label' => 'H4'],
                            ['value' => 'h5', 'label' => 'H5'],
                            ['value' => 'h6', 'label' => 'H6'],
                        ],
                    ],
                    [
                        'key' => 'align',
                        'type' => 'select',
                        'label' => 'Align',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'start', 'label' => 'Start'],
                            ['value' => 'center', 'label' => 'Center'],
                            ['value' => 'end', 'label' => 'End'],
                        ],
                    ],
                ],
            ],
            'text' => [
                'label' => 'Paragraph',
                'category' => 'Typography',
                'max_instances' => null,
                'default_settings' => [
                    'content' => 'Add your paragraph…',
                    'align' => 'start',
                ],
                'settings_fields' => [
                    ['key' => 'content', 'type' => 'textarea', 'label' => 'Content'],
                    [
                        'key' => 'align',
                        'type' => 'select',
                        'label' => 'Align',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'start', 'label' => 'Start'],
                            ['value' => 'center', 'label' => 'Center'],
                            ['value' => 'end', 'label' => 'End'],
                        ],
                    ],
                ],
            ],
            'rich_text' => [
                'label' => 'Rich text',
                'category' => 'Typography',
                'max_instances' => null,
                'default_settings' => [
                    'html' => '<p>Add rich content…</p>',
                ],
                'settings_fields' => [
                    ['key' => 'html', 'type' => 'richtext', 'label' => 'Content'],
                ],
            ],
            'list' => [
                'label' => 'List',
                'category' => 'Typography',
                'max_instances' => null,
                'default_settings' => [
                    'style' => 'ul',
                    'items' => [
                        ['text' => 'Item one'],
                        ['text' => 'Item two'],
                    ],
                ],
                'settings_fields' => [
                    [
                        'key' => 'style',
                        'type' => 'select',
                        'label' => 'Style',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'ul', 'label' => 'Bullets'],
                            ['value' => 'ol', 'label' => 'Numbered'],
                        ],
                    ],
                    [
                        'key' => 'items',
                        'type' => 'repeater',
                        'label' => 'Items',
                        'translatable' => true,
                        'item_fields' => [
                            ['key' => 'text', 'type' => 'text', 'label' => 'Text'],
                        ],
                    ],
                ],
            ],
            'quote' => [
                'label' => 'Quote',
                'category' => 'Typography',
                'max_instances' => null,
                'default_settings' => [
                    'quote' => 'A memorable quote.',
                    'cite' => '',
                ],
                'settings_fields' => [
                    ['key' => 'quote', 'type' => 'textarea', 'label' => 'Quote'],
                    ['key' => 'cite', 'type' => 'text', 'label' => 'Citation'],
                ],
            ],
            'badge' => [
                'label' => 'Badge / eyebrow',
                'category' => 'Typography',
                'max_instances' => null,
                'default_settings' => [
                    'text' => 'New',
                ],
                'settings_fields' => [
                    ['key' => 'text', 'type' => 'text', 'label' => 'Text'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, WidgetDef>
     */
    private static function media(): array
    {
        return [
            'image' => [
                'label' => 'Image',
                'category' => 'Media',
                'max_instances' => null,
                'default_settings' => [
                    'image' => null,
                    'alt' => '',
                    'caption' => '',
                    'link_url' => '',
                    'open_in_new_tab' => false,
                    'lightbox' => false,
                ],
                'settings_fields' => [
                    ['key' => 'image', 'type' => 'media', 'label' => 'Image', 'accept' => 'image/*', 'translatable' => true],
                    ['key' => 'alt', 'type' => 'text', 'label' => 'Alt text'],
                    ['key' => 'caption', 'type' => 'text', 'label' => 'Caption'],
                    ['key' => 'link_url', 'type' => 'url', 'label' => 'Link URL', 'translatable' => false],
                    ['key' => 'open_in_new_tab', 'type' => 'boolean', 'label' => 'Open in new tab', 'translatable' => false],
                    ['key' => 'lightbox', 'type' => 'boolean', 'label' => 'Open in lightbox', 'translatable' => false],
                ],
            ],
            'gallery' => [
                'label' => 'Gallery',
                'category' => 'Media',
                'max_instances' => null,
                'default_settings' => [
                    'images' => [],
                ],
                'settings_fields' => [
                    [
                        'key' => 'images',
                        'type' => 'repeater',
                        'label' => 'Images',
                        'translatable' => false,
                        'item_fields' => [
                            ['key' => 'image', 'type' => 'media', 'label' => 'Image', 'accept' => 'image/*'],
                            ['key' => 'alt', 'type' => 'text', 'label' => 'Alt'],
                        ],
                    ],
                ],
            ],
            'video' => [
                'label' => 'Video',
                'category' => 'Media',
                'max_instances' => null,
                'default_settings' => [
                    'video_url' => '',
                    'aspect' => '16:9',
                ],
                'settings_fields' => [
                    ['key' => 'video_url', 'type' => 'video', 'label' => 'Video URL', 'translatable' => false],
                    [
                        'key' => 'aspect',
                        'type' => 'select',
                        'label' => 'Aspect ratio',
                        'translatable' => false,
                        'options' => [
                            ['value' => '16:9', 'label' => '16:9'],
                            ['value' => '4:3', 'label' => '4:3'],
                            ['value' => '1:1', 'label' => '1:1'],
                        ],
                    ],
                ],
            ],
            'icon' => [
                'label' => 'Icon',
                'category' => 'Media',
                'max_instances' => null,
                'default_settings' => [
                    'icon' => 'star',
                    'size' => 32,
                ],
                'settings_fields' => [
                    ['key' => 'icon', 'type' => 'icon', 'label' => 'Icon', 'translatable' => false],
                    ['key' => 'size', 'type' => 'number', 'label' => 'Size (px)', 'min' => 16, 'max' => 96, 'translatable' => false],
                ],
            ],
            'embed' => [
                'label' => 'Embed / HTML',
                'category' => 'Media',
                'max_instances' => null,
                'default_settings' => [
                    'html' => '',
                ],
                'settings_fields' => [
                    ['key' => 'html', 'type' => 'textarea', 'label' => 'Embed HTML (iframe only)', 'translatable' => false],
                ],
            ],
        ];
    }

    /**
     * @return array<string, WidgetDef>
     */
    private static function actions(): array
    {
        return [
            'button' => [
                'label' => 'Button',
                'category' => 'Actions',
                'max_instances' => null,
                'default_settings' => [
                    'label' => 'Learn more',
                    'url' => '',
                    'style' => 'primary',
                    'open_in_new_tab' => false,
                    'rel' => '',
                    'overlay_id' => null,
                ],
                'settings_fields' => [
                    ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                    ['key' => 'url', 'type' => 'url', 'label' => 'URL', 'translatable' => false],
                    ['key' => 'open_in_new_tab', 'type' => 'boolean', 'label' => 'Open in new tab', 'translatable' => false],
                    ['key' => 'rel', 'type' => 'text', 'label' => 'Rel (noopener noreferrer)', 'translatable' => false],
                    ['key' => 'overlay_id', 'type' => 'number', 'label' => 'Open overlay id (optional)', 'translatable' => false],
                    [
                        'key' => 'style',
                        'type' => 'select',
                        'label' => 'Style',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'primary', 'label' => 'Primary'],
                            ['value' => 'secondary', 'label' => 'Secondary'],
                            ['value' => 'outline', 'label' => 'Outline'],
                            ['value' => 'ghost', 'label' => 'Ghost'],
                        ],
                    ],
                ],
            ],
            'button_group' => [
                'label' => 'Button group',
                'category' => 'Actions',
                'max_instances' => null,
                'default_settings' => [
                    'buttons' => [
                        ['label' => 'Primary', 'url' => '', 'style' => 'primary'],
                        ['label' => 'Secondary', 'url' => '', 'style' => 'outline'],
                    ],
                ],
                'settings_fields' => [
                    [
                        'key' => 'buttons',
                        'type' => 'repeater',
                        'label' => 'Buttons',
                        'translatable' => true,
                        'item_fields' => [
                            ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                            ['key' => 'url', 'type' => 'url', 'label' => 'URL', 'translatable' => false],
                            [
                                'key' => 'style',
                                'type' => 'select',
                                'label' => 'Style',
                                'translatable' => false,
                                'options' => [
                                    ['value' => 'primary', 'label' => 'Primary'],
                                    ['value' => 'secondary', 'label' => 'Secondary'],
                                    ['value' => 'outline', 'label' => 'Outline'],
                                    ['value' => 'ghost', 'label' => 'Ghost'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, WidgetDef>
     */
    private static function layout(): array
    {
        return [
            'spacer' => [
                'label' => 'Spacer',
                'category' => 'Layout',
                'max_instances' => null,
                'default_settings' => [
                    'height' => 48,
                ],
                'settings_fields' => [
                    ['key' => 'height', 'type' => 'number', 'label' => 'Height (px)', 'min' => 8, 'max' => 320, 'translatable' => false],
                ],
            ],
            'divider' => [
                'label' => 'Divider',
                'category' => 'Layout',
                'max_instances' => null,
                'default_settings' => [
                    'style' => 'line',
                    'spacing' => 24,
                ],
                'settings_fields' => [
                    [
                        'key' => 'style',
                        'type' => 'select',
                        'label' => 'Style',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'line', 'label' => 'Line'],
                            ['value' => 'dashed', 'label' => 'Dashed'],
                        ],
                    ],
                    ['key' => 'spacing', 'type' => 'number', 'label' => 'Vertical spacing (px)', 'min' => 0, 'max' => 96, 'translatable' => false],
                ],
            ],
            'anchor' => [
                'label' => 'Anchor',
                'category' => 'Layout',
                'max_instances' => null,
                'default_settings' => [
                    'anchor_id' => 'section',
                ],
                'settings_fields' => [
                    ['key' => 'anchor_id', 'type' => 'text', 'label' => 'Anchor ID', 'translatable' => false],
                ],
            ],
            'breadcrumb' => [
                'label' => 'Breadcrumbs',
                'category' => 'Layout',
                'max_instances' => null,
                'default_settings' => [
                    'home_label' => 'Home',
                    'auto' => true,
                    'items' => [],
                ],
                'settings_fields' => [
                    ['key' => 'home_label', 'type' => 'text', 'label' => 'Home label'],
                    ['key' => 'auto', 'type' => 'boolean', 'label' => 'Use route crumbs', 'translatable' => false],
                    [
                        'key' => 'items',
                        'type' => 'repeater',
                        'label' => 'Override crumbs',
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
     * @return array<string, WidgetDef>
     */
    private static function misc(): array
    {
        return [
            'map' => [
                'label' => 'Map',
                'category' => 'Embeds',
                'max_instances' => null,
                'default_settings' => [
                    'embed_url' => '',
                    'height' => 320,
                ],
                'settings_fields' => [
                    ['key' => 'embed_url', 'type' => 'url', 'label' => 'Embed URL (Google Maps iframe src)', 'translatable' => false],
                    ['key' => 'height', 'type' => 'number', 'label' => 'Height (px)', 'min' => 160, 'max' => 800, 'translatable' => false],
                ],
            ],
            'social_links' => [
                'label' => 'Social links',
                'category' => 'Embeds',
                'max_instances' => null,
                'default_settings' => [
                    'links' => [
                        ['label' => 'Twitter', 'url' => ''],
                        ['label' => 'LinkedIn', 'url' => ''],
                    ],
                ],
                'settings_fields' => [
                    [
                        'key' => 'links',
                        'type' => 'repeater',
                        'label' => 'Links',
                        'translatable' => false,
                        'item_fields' => [
                            ['key' => 'label', 'type' => 'text', 'label' => 'Label'],
                            ['key' => 'url', 'type' => 'url', 'label' => 'URL'],
                            ['key' => 'open_in_new_tab', 'type' => 'boolean', 'label' => 'Open in new tab', 'translatable' => false],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, WidgetDef>
     */
    private static function theme(): array
    {
        $text = static fn (string $label, string $tag): array => [
            'label' => $label,
            'category' => 'Theme',
            'max_instances' => null,
            'default_settings' => ['fallback' => ''],
            'settings_fields' => [
                ['key' => 'fallback', 'type' => 'text', 'label' => 'Fallback text'],
            ],
        ];

        return [
            'post_title' => $text('Post title', 'post.title'),
            'post_excerpt' => $text('Post excerpt', 'post.excerpt'),
            'post_content' => [
                'label' => 'Post content',
                'category' => 'Theme',
                'max_instances' => 1,
                'default_settings' => [],
                'settings_fields' => [],
            ],
            'post_featured_image' => [
                'label' => 'Featured image',
                'category' => 'Theme',
                'max_instances' => 1,
                'default_settings' => ['lightbox' => false],
                'settings_fields' => [
                    ['key' => 'lightbox', 'type' => 'boolean', 'label' => 'Open in lightbox', 'translatable' => false],
                ],
            ],
            'post_info' => [
                'label' => 'Post info',
                'category' => 'Theme',
                'max_instances' => 1,
                'default_settings' => ['show_date' => true, 'show_terms' => true],
                'settings_fields' => [
                    ['key' => 'show_date', 'type' => 'boolean', 'label' => 'Show date', 'translatable' => false],
                    ['key' => 'show_terms', 'type' => 'boolean', 'label' => 'Show terms', 'translatable' => false],
                ],
            ],
            'archive_title' => $text('Archive title', 'archive.title'),
            'loop_grid' => [
                'label' => 'Loop grid',
                'category' => 'Theme',
                'max_instances' => null,
                'default_settings' => [
                    'source' => 'blog',
                    'count' => 6,
                    'order' => 'latest',
                    'pagination' => 'numbers',
                    'mode' => 'grid',
                    'columns' => ['mobile' => 1, 'tablet' => 2, 'desktop' => 3],
                ],
                'settings_fields' => [
                    [
                        'key' => 'source',
                        'type' => 'select',
                        'label' => 'Source',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'blog', 'label' => 'Blog'],
                            ['value' => 'projects', 'label' => 'Projects'],
                            ['value' => 'services', 'label' => 'Services'],
                        ],
                    ],
                    ['key' => 'count', 'type' => 'number', 'label' => 'Count', 'min' => 1, 'max' => 24, 'translatable' => false],
                    [
                        'key' => 'order',
                        'type' => 'select',
                        'label' => 'Order',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'latest', 'label' => 'Latest'],
                            ['value' => 'oldest', 'label' => 'Oldest'],
                            ['value' => 'title', 'label' => 'Title'],
                        ],
                    ],
                    [
                        'key' => 'pagination',
                        'type' => 'select',
                        'label' => 'Pagination',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'none', 'label' => 'None'],
                            ['value' => 'numbers', 'label' => 'Numbers'],
                            ['value' => 'load_more', 'label' => 'Load more'],
                        ],
                    ],
                    [
                        'key' => 'mode',
                        'type' => 'select',
                        'label' => 'Mode',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'grid', 'label' => 'Grid'],
                            ['value' => 'carousel', 'label' => 'Carousel'],
                        ],
                    ],
                    ['key' => 'columns', 'type' => 'responsive_columns', 'label' => 'Columns', 'translatable' => false],
                    ['key' => 'category_ids', 'type' => 'category_multi', 'label' => 'Categories', 'translatable' => false],
                ],
            ],
        ];
    }

    /**
     * @return array<string, WidgetDef>
     */
    private static function extras(): array
    {
        return [
            'lottie' => [
                'label' => 'Lottie',
                'category' => 'Media',
                'max_instances' => null,
                'default_settings' => [
                    'media_id' => null,
                    'loop' => true,
                    'autoplay' => true,
                    'speed' => 1,
                    'play_in_view' => true,
                ],
                'settings_fields' => [
                    ['key' => 'media_id', 'type' => 'media', 'label' => 'Lottie JSON', 'accept' => 'application/json', 'translatable' => false],
                    ['key' => 'loop', 'type' => 'boolean', 'label' => 'Loop', 'translatable' => false],
                    ['key' => 'autoplay', 'type' => 'boolean', 'label' => 'Autoplay', 'translatable' => false],
                    ['key' => 'speed', 'type' => 'number', 'label' => 'Speed', 'min' => 1, 'max' => 3, 'translatable' => false],
                    ['key' => 'play_in_view', 'type' => 'boolean', 'label' => 'Play when in view', 'translatable' => false],
                ],
            ],
            'flip_box' => [
                'label' => 'Flip box',
                'category' => 'Media',
                'max_instances' => null,
                'default_settings' => [
                    'front_heading' => 'Front',
                    'front_text' => '',
                    'front_icon' => 'star',
                    'back_heading' => 'Back',
                    'back_text' => '',
                    'back_label' => 'Learn more',
                    'back_url' => '',
                ],
                'settings_fields' => [
                    ['key' => 'front_heading', 'type' => 'text', 'label' => 'Front heading'],
                    ['key' => 'front_text', 'type' => 'textarea', 'label' => 'Front text'],
                    ['key' => 'front_icon', 'type' => 'icon', 'label' => 'Front icon', 'translatable' => false],
                    ['key' => 'front_image', 'type' => 'media', 'label' => 'Front image', 'accept' => 'image/*', 'translatable' => false],
                    ['key' => 'back_heading', 'type' => 'text', 'label' => 'Back heading'],
                    ['key' => 'back_text', 'type' => 'textarea', 'label' => 'Back text'],
                    ['key' => 'back_label', 'type' => 'text', 'label' => 'Button label'],
                    ['key' => 'back_url', 'type' => 'url', 'label' => 'Button URL', 'translatable' => false],
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
                'kind' => PageLeafRegistry::KIND_WIDGET,
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
