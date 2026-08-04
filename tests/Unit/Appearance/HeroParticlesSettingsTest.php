<?php

namespace Tests\Unit\Appearance;

use App\Services\Appearance\HeroParticlesSettings;
use Tests\TestCase;

class HeroParticlesSettingsTest extends TestCase
{
    public function test_normalize_clamps_scales_and_color(): void
    {
        $out = HeroParticlesSettings::normalizeInto([
            'particles_enabled' => '0',
            'particles_density' => 999,
            'particles_speed' => -3,
            'particles_opacity' => '40',
            'particles_size' => 12,
            'particles_color' => '#ABC',
        ]);

        $this->assertFalse($out['particles_enabled']);
        $this->assertSame(100, $out['particles_density']);
        $this->assertSame(1, $out['particles_speed']);
        $this->assertSame(40, $out['particles_opacity']);
        $this->assertSame(12, $out['particles_size']);
        $this->assertSame('#abc', $out['particles_color']);
    }

    public function test_invalid_color_becomes_empty(): void
    {
        $out = HeroParticlesSettings::normalizeInto([
            'particles_color' => 'red',
        ]);
        $this->assertSame('', $out['particles_color']);
    }
}
