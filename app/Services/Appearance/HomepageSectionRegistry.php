<?php

namespace App\Services\Appearance;

/**
 * Canonical homepage section types for the Appearance builder.
 *
 * @phpstan-type SectionTypeDef array{label: string, max_instances: int|null, default_settings: array<string, mixed>}
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
            ],
            'case_studies' => [
                'label' => 'Case studies',
                'max_instances' => 1,
                'default_settings' => [
                    'title' => 'Selected work',
                    'subtitle' => 'Recent projects we are proud of.',
                    'limit' => 3,
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
            ],
            'tech_stack' => [
                'label' => 'Tech stack',
                'max_instances' => 1,
                'default_settings' => [
                    'eyebrow' => 'Built with',
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
            ],
            'cta' => [
                'label' => 'Call to action',
                'max_instances' => 1,
                'default_settings' => [
                    'title' => 'Ready to start your project?',
                    'subtitle' => "Tell us about your idea. We'll get back within 24 hours.",
                    'button_label' => 'Get in touch',
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
