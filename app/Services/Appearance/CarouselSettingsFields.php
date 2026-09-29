<?php

namespace App\Services\Appearance;

/**
 * Grid/carousel layout settings shared by listing widgets and kits (e.g. testimonials),
 * so the same keys render through the shared frontend carousel options parser.
 */
final class CarouselSettingsFields
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'layout' => 'grid',
            'autoplay' => false,
            'autoplay_seconds' => 6,
            'arrows_position' => 'sides',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function layoutField(): array
    {
        return [
            'key' => 'layout',
            'type' => 'select',
            'label' => 'Layout',
            'translatable' => false,
            'options' => [
                ['value' => 'grid', 'label' => 'Grid'],
                ['value' => 'carousel', 'label' => 'Carousel'],
            ],
        ];
    }

    /**
     * Carousel-only controls (autoplay + arrows position).
     *
     * @return list<array<string, mixed>>
     */
    public static function carouselFields(): array
    {
        return [
            ['key' => 'autoplay', 'type' => 'boolean', 'label' => 'Autoplay (carousel)', 'translatable' => false],
            ['key' => 'autoplay_seconds', 'type' => 'number', 'label' => 'Autoplay interval (seconds)', 'min' => 3, 'max' => 15, 'translatable' => false],
            [
                'key' => 'arrows_position',
                'type' => 'select',
                'label' => 'Arrows position (carousel)',
                'translatable' => false,
                'options' => [
                    ['value' => 'sides', 'label' => 'Sides'],
                    ['value' => 'top_left', 'label' => 'Top left'],
                    ['value' => 'top_center', 'label' => 'Top center'],
                    ['value' => 'top_right', 'label' => 'Top right'],
                    ['value' => 'top_between', 'label' => 'Top space between'],
                    ['value' => 'bottom_left', 'label' => 'Bottom left'],
                    ['value' => 'bottom_center', 'label' => 'Bottom center'],
                    ['value' => 'bottom_right', 'label' => 'Bottom right'],
                    ['value' => 'bottom_between', 'label' => 'Bottom space between'],
                ],
            ],
        ];
    }
}
