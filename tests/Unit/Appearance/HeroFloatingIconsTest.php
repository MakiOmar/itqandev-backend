<?php

namespace Tests\Unit\Appearance;

use App\Services\Appearance\HeroFloatingIcons;
use Tests\TestCase;

class HeroFloatingIconsTest extends TestCase
{
    public function test_normalize_clamps_and_filters_motion(): void
    {
        $out = HeroFloatingIcons::normalize([
            [
                'id' => 'a',
                'enabled' => true,
                'media_id' => '12',
                'motion' => 'spin',
                'x' => -50,
                'y' => 150,
                'size' => 999,
            ],
            [
                'media_id' => 0,
                'motion' => 'bounce',
                'x' => 50,
                'y' => 50,
                'size' => 40,
            ],
        ]);

        $this->assertCount(2, $out);
        $this->assertSame('rotate', $out[0]['motion']);
        $this->assertSame(HeroFloatingIcons::POSITION_MIN, $out[0]['x']);
        $this->assertSame(HeroFloatingIcons::POSITION_MAX, $out[0]['y']);
        $this->assertSame(120, $out[0]['size']);
        $this->assertSame(12, $out[0]['media_id']);
        $this->assertNull($out[1]['media_id']);
        $this->assertSame('bounce', $out[1]['motion']);
    }

    public function test_normalize_allows_outside_edge_positions(): void
    {
        $out = HeroFloatingIcons::normalize([
            ['id' => 'edge', 'x' => -8, 'y' => 108, 'size' => 48],
        ]);

        $this->assertSame(-8.0, $out[0]['x']);
        $this->assertSame(108.0, $out[0]['y']);
    }

    public function test_normalize_caps_max_icons(): void
    {
        $rows = [];
        for ($i = 0; $i < HeroFloatingIcons::MAX_ICONS + 5; $i++) {
            $rows[] = ['id' => 'i'.$i, 'media_id' => $i + 1];
        }
        $out = HeroFloatingIcons::normalize($rows);
        $this->assertCount(HeroFloatingIcons::MAX_ICONS, $out);
    }
}
