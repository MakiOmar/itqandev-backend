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
}
