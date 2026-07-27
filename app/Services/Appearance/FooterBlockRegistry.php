<?php

namespace App\Services\Appearance;

/**
 * Footer-specific block types (not homepage section types).
 *
 * @phpstan-type BlockTypeDef array{label: string, max_instances: int|null, default_settings: array<string, mixed>}
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
            ],
            'contact' => [
                'label' => 'Contact',
                'max_instances' => 1,
                'default_settings' => [
                    'title' => 'Contact',
                    'show_email' => true,
                ],
            ],
            'social' => [
                'label' => 'Social links',
                'max_instances' => 1,
                'default_settings' => [
                    'title' => 'Follow us',
                ],
            ],
            'menu' => [
                'label' => 'Menu',
                'max_instances' => null,
                'default_settings' => [
                    'title' => 'Quick links',
                    'menu_slug' => 'primary',
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
            ],
            'rich_text' => [
                'label' => 'Rich text',
                'max_instances' => null,
                'default_settings' => [
                    'title' => '',
                    'body' => '',
                ],
            ],
            'cta' => [
                'label' => 'Call to action',
                'max_instances' => 1,
                'default_settings' => [
                    'button_label' => 'Get in touch',
                    'button_url' => '/contact',
                ],
            ],
        ];
    }

    public static function has(string $type): bool
    {
        return array_key_exists($type, self::all());
    }

    /**
     * @return list<array{type: string, label: string, max_instances: int|null}>
     */
    public static function forAdmin(): array
    {
        $out = [];
        foreach (self::all() as $type => $def) {
            $out[] = [
                'type' => $type,
                'label' => $def['label'],
                'max_instances' => $def['max_instances'],
            ];
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
