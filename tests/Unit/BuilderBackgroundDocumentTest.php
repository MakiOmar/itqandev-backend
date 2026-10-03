<?php

namespace Tests\Unit;

use App\Services\Appearance\BuilderBackgroundDocument;
use PHPUnit\Framework\TestCase;

class BuilderBackgroundDocumentTest extends TestCase
{
    public function test_image_background_keeps_media_id_and_overlay(): void
    {
        $out = BuilderBackgroundDocument::normalize([
            'type' => 'image',
            'image_url' => '/storage/media/hero.webp',
            'image_id' => '42',
            'overlay' => true,
            'overlay_color' => '#0F172A',
            'overlay_opacity' => 140,
        ]);

        $this->assertSame('/storage/media/hero.webp', $out['image_url']);
        $this->assertSame(42, $out['image_id']);
        $this->assertTrue($out['overlay']);
        $this->assertSame('#0f172a', $out['overlay_color']);
        $this->assertSame(100, $out['overlay_opacity']);
    }

    public function test_backdrop_blur_is_clamped_and_omitted_when_off(): void
    {
        $blurred = BuilderBackgroundDocument::normalize(['type' => 'color', 'color' => '#ffffffcc', 'backdrop_blur' => '99']);
        $this->assertSame(BuilderBackgroundDocument::MAX_BACKDROP_BLUR, $blurred['backdrop_blur']);

        $gradient = BuilderBackgroundDocument::normalize(['type' => 'gradient', 'backdrop_blur' => 8]);
        $this->assertSame(8, $gradient['backdrop_blur']);

        foreach ([0, -5, 'abc', null] as $off) {
            $out = BuilderBackgroundDocument::normalize(['type' => 'color', 'color' => '#000000', 'backdrop_blur' => $off]);
            $this->assertArrayNotHasKey('backdrop_blur', $out);
        }

        $this->assertSame(['type' => 'none'], BuilderBackgroundDocument::normalize(['type' => 'none', 'backdrop_blur' => 10]));
    }

    public function test_overlay_fields_are_dropped_when_overlay_is_off_and_invalid_values_fall_back(): void
    {
        $off = BuilderBackgroundDocument::normalize([
            'type' => 'image',
            'image_url' => 'https://cdn.example.com/a.jpg',
            'image_id' => -3,
            'overlay' => false,
            'overlay_color' => '#ffffff',
        ]);
        $this->assertArrayNotHasKey('image_id', $off);
        $this->assertArrayNotHasKey('overlay', $off);
        $this->assertArrayNotHasKey('overlay_color', $off);

        $invalid = BuilderBackgroundDocument::normalize([
            'type' => 'image',
            'image_url' => 'javascript:alert(1)',
            'overlay' => '1',
            'overlay_color' => 'red; background:url(x)',
            'overlay_opacity' => 'lots',
        ]);
        $this->assertArrayNotHasKey('image_url', $invalid);
        $this->assertSame('#000000', $invalid['overlay_color']);
        $this->assertSame(50, $invalid['overlay_opacity']);
    }

    public function test_image_lazy_is_only_stored_when_disabled(): void
    {
        $default = BuilderBackgroundDocument::normalize(['type' => 'image', 'image_url' => '/storage/a.webp']);
        $this->assertArrayNotHasKey('image_lazy', $default);

        $on = BuilderBackgroundDocument::normalize(['type' => 'image', 'image_url' => '/storage/a.webp', 'image_lazy' => true]);
        $this->assertArrayNotHasKey('image_lazy', $on);

        $off = BuilderBackgroundDocument::normalize(['type' => 'image', 'image_url' => '/storage/a.webp', 'image_lazy' => false]);
        $this->assertFalse($off['image_lazy']);

        $color = BuilderBackgroundDocument::normalize(['type' => 'color', 'color' => '#ffffff', 'image_lazy' => false]);
        $this->assertArrayNotHasKey('image_lazy', $color);
    }

    public function test_dark_overrides_keep_only_keys_for_the_background_type(): void
    {
        $color = BuilderBackgroundDocument::normalize([
            'type' => 'color',
            'color' => '#ffffff',
            'dark' => ['color' => '#0F172A', 'gradient_from' => '#000000', 'image_url' => '/storage/x.webp'],
        ]);
        $this->assertSame(['color' => '#0f172a'], $color['dark']);

        $gradient = BuilderBackgroundDocument::normalize([
            'type' => 'gradient',
            'dark' => ['gradient_from' => '#111111', 'gradient_to' => 'nope', 'gradient_angle' => 10],
        ]);
        $this->assertSame(['gradient_from' => '#111111'], $gradient['dark']);

        $particles = BuilderBackgroundDocument::normalize([
            'type' => 'particles',
            'dark' => ['particles_color' => '#38bdf8', 'particles_speed' => 90],
        ]);
        $this->assertSame(['particles_color' => '#38bdf8'], $particles['dark']);

        $rain = BuilderBackgroundDocument::normalize([
            'type' => 'animated_rain',
            'dark' => ['rain_color' => '#ffffff80'],
        ]);
        $this->assertSame(['rain_color' => '#ffffff80'], $rain['dark']);
    }

    public function test_dark_image_override_sanitizes_url_overlay_and_drops_orphan_id(): void
    {
        $image = BuilderBackgroundDocument::normalize([
            'type' => 'image',
            'image_url' => '/storage/light.webp',
            'dark' => [
                'image_url' => '/storage/dark.webp',
                'image_id' => '42',
                'overlay_color' => '#000000',
                'overlay_opacity' => 140,
                'image_size' => 'contain',
            ],
        ]);
        $this->assertSame([
            'image_url' => '/storage/dark.webp',
            'image_id' => 42,
            'overlay_color' => '#000000',
            'overlay_opacity' => 100,
        ], $image['dark']);

        $unsafe = BuilderBackgroundDocument::normalize([
            'type' => 'image',
            'image_url' => '/storage/light.webp',
            'dark' => ['image_url' => 'javascript:alert(1)', 'image_id' => 7, 'overlay_color' => 'red;}'],
        ]);
        $this->assertArrayNotHasKey('dark', $unsafe);
    }

    public function test_particles_and_rain_reject_kit_vars_but_css_colours_accept_them(): void
    {
        $color = BuilderBackgroundDocument::normalize([
            'type' => 'color',
            'color' => 'var(--kit-color-surface)',
            'dark' => ['color' => 'var(--kit-color-surface-dark)'],
        ]);
        $this->assertSame('var(--kit-color-surface)', $color['color']);
        $this->assertSame('var(--kit-color-surface-dark)', $color['dark']['color']);

        $particles = BuilderBackgroundDocument::normalize([
            'type' => 'particles',
            'particles_color' => 'var(--kit-color-primary)',
            'dark' => ['particles_color' => 'var(--kit-color-primary)'],
        ]);
        $this->assertArrayNotHasKey('particles_color', $particles);
        $this->assertArrayNotHasKey('dark', $particles);
    }

    public function test_none_background_drops_dark_overrides(): void
    {
        $this->assertSame(
            ['type' => 'none'],
            BuilderBackgroundDocument::normalize(['type' => 'none', 'dark' => ['color' => '#000000']]),
        );
    }
}
