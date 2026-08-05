<?php

namespace App\Services\Appearance;

/**
 * Canonical homepage section types for the Appearance builder.
 *
 * @phpstan-type SettingsField array{key: string, type: string, label: string, accept?: string, min?: int, max?: int}
 * @phpstan-type SectionTypeDef array{label: string, max_instances: int|null, default_settings: array<string, mixed>, settings_fields: list<SettingsField>}
 */
final class HomepageSectionRegistry
{
    /**
     * @return array<string, SectionTypeDef>
     */
    public static function all(): array
    {
        return [
            'hero' => [
                'label' => 'Hero',
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
                'max_instances' => 1,
                'default_settings' => [
                    'title' => 'Ready to start your project?',
                    'subtitle' => "Tell us about your idea. We'll get back within 24 hours.",
                    'button_label' => 'Get in touch',
                ],
                'settings_fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title'],
                    ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'Subtitle'],
                    ['key' => 'button_label', 'type' => 'text', 'label' => 'Button label'],
                ],
            ],
            'form' => [
                'label' => 'Form',
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
