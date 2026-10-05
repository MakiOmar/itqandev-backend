<?php

namespace App\Services\Appearance;

/**
 * Atomic page Widgets for the page builder.
 *
 * @phpstan-type SettingsField array{key: string, type: string, label: string, accept?: string, min?: int, max?: int, options?: list<array{value: string, label: string}>, item_fields?: list<SettingsField>, translatable?: bool}
 * @phpstan-type WidgetDef array{label: string, category: string, default_settings: array<string, mixed>, settings_fields: list<SettingsField>}
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
     * Trust badges: shared rows (icon + per-locale text) with divider and responsive layout options.
     *
     * @param  callable(string, string): array<string, mixed>  $bool
     * @return WidgetDef
     */
    private static function trustBadges(callable $bool): array
    {
        $stroke = 'fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"';
        $badge = static fn (string $id, string $name, string $body, string $text): array => [
            'id' => $id,
            'icon' => ['library' => 'lucide', 'name' => $name, 'body' => $body, 'view_box' => '0 0 24 24'],
            'text' => $text,
        ];
        $select = static fn (string $key, string $label, array $options): array => [
            'key' => $key,
            'type' => 'select',
            'label' => $label,
            'translatable' => false,
            'options' => array_map(static fn (string $v, string $l): array => ['value' => $v, 'label' => $l], array_keys($options), $options),
        ];

        return [
            'label' => 'Trust badges',
            'category' => 'Content',
            'default_settings' => [
                'badges' => [
                    $badge('itm_secure', 'shield-check', '<g '.$stroke.'><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12l2 2l4-4"/></g>', 'Secure payments'),
                    $badge('itm_shipping', 'truck', '<g '.$stroke.'><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2m10 0H9m10 0h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></g>', 'Fast delivery'),
                    $badge('itm_returns', 'rotate-ccw', '<g '.$stroke.'><path d="M3 12a9 9 0 1 0 9-9a9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></g>', 'Easy returns'),
                ],
                'icon_position' => 'inline',
                'icon_size' => 28,
                'align' => 'center',
                'mobile_layout' => 'stacked',
                'tablet_layout' => 'inline',
                'show_dividers' => true,
                'divider_color' => '',
                'divider_height' => 32,
                'divider_style' => 'solid',
            ],
            'settings_fields' => [
                [
                    'key' => 'badges',
                    'type' => 'repeater',
                    'label' => 'Badges',
                    'translatable' => false,
                    'item_fields' => [
                        ['key' => 'icon', 'type' => 'icon', 'label' => 'Icon', 'translatable' => false],
                        ['key' => 'text', 'type' => 'text', 'label' => 'Text', 'translatable' => true],
                    ],
                ],
                $select('icon_position', 'Icon and text', ['inline' => 'Inline', 'stacked' => 'Stacked']),
                ['key' => 'icon_size', 'type' => 'number', 'label' => 'Icon size (px)', 'min' => 16, 'max' => 96, 'translatable' => false],
                $select('align', 'Alignment', ['start' => 'Start', 'center' => 'Center', 'end' => 'End']),
                $select('mobile_layout', 'Mobile layout', ['stacked' => 'Stacked', 'two_columns' => 'Two per row']),
                $select('tablet_layout', 'Tablet layout', ['inline' => 'One row', 'two_columns' => 'Two per row']),
                $bool('show_dividers', 'Show dividers'),
                ['key' => 'divider_color', 'type' => 'color', 'label' => 'Divider color (empty = theme)', 'translatable' => false],
                ['key' => 'divider_height', 'type' => 'number', 'label' => 'Divider height (px)', 'min' => 8, 'max' => 120, 'translatable' => false],
                $select('divider_style', 'Divider style', ['solid' => 'Solid', 'dashed' => 'Dashed', 'dotted' => 'Dots', 'dash_dot' => 'Dash dot']),
            ],
        ];
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
                'default_settings' => [
                    'title' => '',
                    'subtitle' => '',
                    'limit' => 6,
                    'columns' => ['mobile' => 1, 'tablet' => 2, 'desktop' => 3],
                    'card_style' => 'card',
                    'show_rating' => true,
                    'show_avatar' => true,
                    'show_role' => true,
                    'show_project' => true,
                    ...CarouselSettingsFields::defaults(),
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'translatable' => true],
                    ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Subtitle', 'translatable' => true],
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
                    ...CarouselSettingsFields::fields(),
                ],
            ],
            'trust_badges' => self::trustBadges($bool),
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
                'default_settings' => [
                    'text' => 'New',
                ],
                'settings_fields' => [
                    ['key' => 'text', 'type' => 'text', 'label' => 'Text'],
                ],
            ],
            'text_logo' => self::textLogo(),
        ];
    }

    /**
     * Text logo: mark tile (short text or icon) beside a brand name with an emphasised `[[part]]` and a tagline.
     *
     * @return WidgetDef
     */
    private static function textLogo(): array
    {
        $select = static fn (string $key, string $label, array $options): array => [
            'key' => $key,
            'type' => 'select',
            'label' => $label,
            'translatable' => false,
            'options' => array_map(static fn (string $v, string $l): array => ['value' => $v, 'label' => $l], array_keys($options), $options),
        ];

        return [
            'label' => 'Text logo',
            'category' => 'Typography',
            'default_settings' => [
                'name' => 'BRAND[[NAME]]',
                'tagline' => 'Web & App Development',
                'mark_type' => 'text',
                'mark_text' => 'BR',
                'mark_icon' => '',
                'layout' => 'inline',
                'link_url' => '',
            ],
            'settings_fields' => [
                ['key' => 'name', 'type' => 'text', 'label' => 'Name (wrap the emphasised part in [[ ]])', 'translatable' => true],
                ['key' => 'tagline', 'type' => 'text', 'label' => 'Tagline (optional)', 'translatable' => true],
                $select('mark_type', 'Mark', ['text' => 'Short text', 'icon' => 'Icon', 'none' => 'None']),
                ['key' => 'mark_text', 'type' => 'text', 'label' => 'Mark text (1–4 characters)', 'translatable' => true],
                ['key' => 'mark_icon', 'type' => 'icon', 'label' => 'Mark icon', 'translatable' => false],
                $select('layout', 'Layout', ['inline' => 'Mark beside text', 'stacked' => 'Mark above text']),
                ['key' => 'link_url', 'type' => 'text', 'label' => 'Link URL (empty = home page)', 'translatable' => false],
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
                // Size lives on the icon value (picker "Icon size"); a saved legacy `size` is only a render fallback.
                'default_settings' => [
                    'icon' => 'star',
                    'size' => 32,
                ],
                'settings_fields' => [
                    ['key' => 'icon', 'type' => 'icon', 'label' => 'Icon', 'translatable' => false],
                ],
            ],
            'embed' => [
                'label' => 'Embed / HTML',
                'category' => 'Media',
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
            'theme_switch' => self::themeSwitch(),
            'contact_float' => self::contactFloat(),
        ];
    }

    /**
     * @param  array<string, string>  $options
     * @return SettingsField
     */
    private static function selectField(string $key, string $label, array $options, array $extra = []): array
    {
        return [
            'key' => $key,
            'type' => 'select',
            'label' => $label,
            'translatable' => false,
            'options' => array_map(static fn (string $v, string $l): array => ['value' => $v, 'label' => $l], array_keys($options), $options),
            ...$extra,
        ];
    }

    /**
     * Corner + offset fields shared by widgets that can float over the page.
     *
     * @return list<SettingsField>
     */
    private static function floatingPositionFields(string $group, ?string $showIf): array
    {
        $extra = array_filter(['group' => $group, 'show_if' => $showIf]);

        return [
            self::selectField('corner', 'Corner', [
                'bottom_end' => 'Bottom end (right in LTR)',
                'bottom_start' => 'Bottom start (left in LTR)',
                'top_end' => 'Top end',
                'top_start' => 'Top start',
            ], $extra),
            ['key' => 'offset_x', 'type' => 'number', 'label' => 'Side offset (px)', 'min' => 0, 'max' => 200, 'translatable' => false, ...$extra],
            ['key' => 'offset_y', 'type' => 'number', 'label' => 'Top / bottom offset (px)', 'min' => 0, 'max' => 200, 'translatable' => false, ...$extra],
        ];
    }

    /**
     * Light/dark theme switch: icon button or sliding switch, inline or floating in a corner.
     *
     * @return WidgetDef
     */
    private static function themeSwitch(): array
    {
        $icon = static fn (string $name, string $body): array => ['library' => 'lucide', 'name' => $name, 'body' => $body, 'view_box' => '0 0 24 24'];

        return [
            'label' => 'Theme switch',
            'category' => 'Actions',
            'default_settings' => [
                'variant' => 'icon',
                'light_icon' => $icon('moon', '<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401"/>'),
                'dark_icon' => $icon('sun', '<g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></g>'),
                'show_label' => false,
                'light_label' => 'Dark mode',
                'dark_label' => 'Light mode',
                'floating' => false,
                'corner' => 'bottom_end',
                'offset_x' => 24,
                'offset_y' => 24,
                'translations' => [
                    'ar' => ['light_label' => 'الوضع الداكن', 'dark_label' => 'الوضع الفاتح'],
                ],
            ],
            'settings_fields' => [
                self::selectField('variant', 'Style', ['icon' => 'Icon button', 'switch' => 'Sliding switch'], ['group' => 'content']),
                ['key' => 'light_icon', 'type' => 'icon', 'label' => 'Icon in light mode', 'translatable' => false, 'group' => 'content'],
                ['key' => 'dark_icon', 'type' => 'icon', 'label' => 'Icon in dark mode', 'translatable' => false, 'group' => 'content'],
                ['key' => 'light_label', 'type' => 'text', 'label' => 'Label in light mode (also the screen reader text)', 'translatable' => true, 'group' => 'content'],
                ['key' => 'dark_label', 'type' => 'text', 'label' => 'Label in dark mode (also the screen reader text)', 'translatable' => true, 'group' => 'content'],
                ['key' => 'show_label', 'type' => 'boolean', 'label' => 'Show label next to the icon', 'translatable' => false, 'group' => 'content'],
                ['key' => 'floating', 'type' => 'boolean', 'label' => 'Float in a screen corner', 'translatable' => false, 'group' => 'position'],
                ...self::floatingPositionFields('position', 'floating'),
            ],
        ];
    }

    /**
     * Floating contact launcher: opens a panel with a WhatsApp chat link and an optional builder form.
     *
     * @return WidgetDef
     */
    private static function contactFloat(): array
    {
        return [
            'label' => 'Floating contact',
            'category' => 'Actions',
            'default_settings' => [
                'launcher_icon' => [
                    'library' => 'lucide',
                    'name' => 'message-circle',
                    'body' => '<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092a10 10 0 1 0-4.777-4.719"/>',
                    'view_box' => '0 0 24 24',
                ],
                'launcher_label' => '',
                'panel_title' => 'Contact us',
                'panel_text' => 'We usually reply within a few hours.',
                'whatsapp_enabled' => true,
                'whatsapp_number' => '',
                'whatsapp_label' => 'Chat on WhatsApp',
                'whatsapp_message' => 'Hello! I would like to know more about your services.',
                'message_enabled' => true,
                'message_label' => 'Leave us a message',
                'form_slug' => '',
                'corner' => 'bottom_end',
                'offset_x' => 24,
                'offset_y' => 24,
                'translations' => [
                    'ar' => [
                        'panel_title' => 'تواصل معنا',
                        'panel_text' => 'نرد عادةً خلال ساعات قليلة.',
                        'whatsapp_label' => 'تحدث معنا عبر واتساب',
                        'whatsapp_message' => 'مرحباً! أود معرفة المزيد عن خدماتكم.',
                        'message_label' => 'اترك لنا رسالة',
                    ],
                ],
            ],
            'settings_fields' => [
                ['key' => 'launcher_icon', 'type' => 'icon', 'label' => 'Button icon', 'translatable' => false, 'group' => 'launcher'],
                ['key' => 'launcher_label', 'type' => 'text', 'label' => 'Button text (optional)', 'translatable' => true, 'group' => 'launcher'],
                ['key' => 'panel_title', 'type' => 'text', 'label' => 'Panel title', 'translatable' => true, 'group' => 'launcher'],
                ['key' => 'panel_text', 'type' => 'textarea', 'label' => 'Panel intro', 'translatable' => true, 'group' => 'launcher'],
                ['key' => 'whatsapp_enabled', 'type' => 'boolean', 'label' => 'Show WhatsApp', 'translatable' => false, 'group' => 'whatsapp'],
                ['key' => 'whatsapp_number', 'type' => 'text', 'label' => 'WhatsApp number (international, digits only, e.g. 9665XXXXXXXX)', 'translatable' => false, 'group' => 'whatsapp', 'show_if' => 'whatsapp_enabled'],
                ['key' => 'whatsapp_label', 'type' => 'text', 'label' => 'Button label', 'translatable' => true, 'group' => 'whatsapp', 'show_if' => 'whatsapp_enabled'],
                ['key' => 'whatsapp_message', 'type' => 'textarea', 'label' => 'Pre-filled message', 'translatable' => true, 'group' => 'whatsapp', 'show_if' => 'whatsapp_enabled'],
                ['key' => 'message_enabled', 'type' => 'boolean', 'label' => 'Show "leave a message"', 'translatable' => false, 'group' => 'message'],
                ['key' => 'message_label', 'type' => 'text', 'label' => 'Button label', 'translatable' => true, 'group' => 'message', 'show_if' => 'message_enabled'],
                ['key' => 'form_slug', 'type' => 'form', 'label' => 'Form (empty = link to the contact page)', 'translatable' => false, 'group' => 'message', 'show_if' => 'message_enabled'],
                ...self::floatingPositionFields('position', null),
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
                'default_settings' => [],
                'settings_fields' => [],
            ],
            'post_featured_image' => [
                'label' => 'Featured image',
                'category' => 'Theme',
                'default_settings' => ['lightbox' => false],
                'settings_fields' => [
                    ['key' => 'lightbox', 'type' => 'boolean', 'label' => 'Open in lightbox', 'translatable' => false],
                ],
            ],
            'post_info' => [
                'label' => 'Post info',
                'category' => 'Theme',
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
     * @return list<array{type: string, kind: string, label: string, category: string, default_settings: array<string, mixed>, settings_fields: list<SettingsField>}>
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
