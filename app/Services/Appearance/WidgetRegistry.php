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
        );
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
                    'object_fit' => 'cover',
                    'radius' => 'lg',
                ],
                'settings_fields' => [
                    ['key' => 'image', 'type' => 'media', 'label' => 'Image', 'accept' => 'image/*', 'translatable' => true],
                    ['key' => 'alt', 'type' => 'text', 'label' => 'Alt text'],
                    ['key' => 'caption', 'type' => 'text', 'label' => 'Caption'],
                    ['key' => 'link_url', 'type' => 'url', 'label' => 'Link URL', 'translatable' => false],
                    [
                        'key' => 'object_fit',
                        'type' => 'select',
                        'label' => 'Object fit',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'cover', 'label' => 'Cover'],
                            ['value' => 'contain', 'label' => 'Contain'],
                        ],
                    ],
                    [
                        'key' => 'radius',
                        'type' => 'select',
                        'label' => 'Radius',
                        'translatable' => false,
                        'options' => [
                            ['value' => 'none', 'label' => 'None'],
                            ['value' => 'md', 'label' => 'Medium'],
                            ['value' => 'lg', 'label' => 'Large'],
                            ['value' => 'full', 'label' => 'Pill'],
                        ],
                    ],
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
                ],
                'settings_fields' => [
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
