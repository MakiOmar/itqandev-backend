<?php

namespace App\Services\Appearance;

/**
 * Footer-specific block types (not homepage section types).
 *
 * @phpstan-type SettingsField array{key: string, type: string, label: string, accept?: string, min?: int, max?: int}
 * @phpstan-type BlockTypeDef array{label: string, max_instances: int|null, default_settings: array<string, mixed>, settings_fields: list<SettingsField>}
 */
final class FooterBlockRegistry
{
    /**
     * @return array<string, BlockTypeDef>
     */
    public static function all(): array
    {
        return [
            'brand' => [
                'label' => 'Brand',
                'max_instances' => 1,
                'default_settings' => [
                    'tagline' => 'Web, Android & iOS development. We build digital products that scale.',
                    'show_logo' => true,
                    'show_name' => true,
                ],
                'settings_fields' => [
                    ['key' => 'tagline', 'type' => 'textarea', 'label' => 'Tagline'],
                    ['key' => 'show_logo', 'type' => 'boolean', 'label' => 'Show logo'],
                    ['key' => 'show_name', 'type' => 'boolean', 'label' => 'Show name'],
                ],
            ],
            'contact' => [
                'label' => 'Contact',
                'max_instances' => 1,
                'default_settings' => [
                    'title' => 'Contact',
                    'show_email' => true,
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'show_email', 'type' => 'boolean', 'label' => 'Show email'],
                ],
            ],
            'social' => [
                'label' => 'Social links',
                'max_instances' => 1,
                'default_settings' => [
                    'title' => 'Follow us',
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                ],
            ],
            'menu' => [
                'label' => 'Menu',
                'max_instances' => null,
                'default_settings' => [
                    'title' => 'Quick links',
                    'menu_slug' => 'primary',
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'menu_slug', 'type' => 'text', 'label' => 'Menu slug', 'translatable' => false],
                ],
            ],
            'links' => [
                'label' => 'Custom links',
                'max_instances' => null,
                'default_settings' => [
                    'title' => 'Links',
                    'links' => [
                        ['id' => 'lnk_services', 'label' => 'Services', 'url' => '/services'],
                        ['id' => 'lnk_work', 'label' => 'Work', 'url' => '/work'],
                        ['id' => 'lnk_about', 'label' => 'About', 'url' => '/about'],
                        ['id' => 'lnk_contact', 'label' => 'Contact', 'url' => '/contact'],
                    ],
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'links', 'type' => 'json', 'label' => 'Links (JSON array of {id,label,url})'],
                ],
            ],
            'rich_text' => [
                'label' => 'Rich text',
                'max_instances' => null,
                'default_settings' => [
                    'title' => '',
                    'body' => '',
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'body', 'type' => 'textarea', 'label' => 'Body'],
                ],
            ],
            'cta' => [
                'label' => 'Call to action',
                'max_instances' => 1,
                'default_settings' => [
                    'button_label' => 'Get in touch',
                    'button_url' => '/contact',
                ],
                'settings_fields' => [
                    ['key' => 'button_label', 'type' => 'text', 'label' => 'Button label'],
                    ['key' => 'button_url', 'type' => 'text', 'label' => 'Button URL', 'translatable' => false],
                ],
            ],
        ];
    }

    public static function has(string $type): bool
    {
        return array_key_exists($type, self::all());
    }

    /**
     * @return list<array{type: string, label: string, max_instances: int|null, default_settings: array<string, mixed>, settings_fields: list<SettingsField>}>
     */
    public static function forAdmin(): array
    {
        $out = [];
        foreach (self::all() as $type => $def) {
            $out[] = [
                'type' => $type,
                'label' => $def['label'],
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
