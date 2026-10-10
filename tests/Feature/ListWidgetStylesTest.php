<?php

namespace Tests\Feature;

use App\Services\Appearance\BuilderStyleDocument;
use App\Services\Appearance\PageLayoutDocument;
use App\Services\Appearance\WidgetRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListWidgetStylesTest extends TestCase
{
    use RefreshDatabase;

    private const ICON = ['library' => 'lucide', 'name' => 'check', 'body' => '<path fill="none" stroke="currentColor" d="M20 6L9 17l-5-5"/>', 'view_box' => '0 0 24 24'];

    public function test_registry_offers_a_non_translatable_marker_icon(): void
    {
        $fields = collect(WidgetRegistry::all()['list']['settings_fields']);

        $this->assertSame('icon', $fields->firstWhere('key', 'marker_icon')['type']);
        $this->assertNotContains('marker_icon', WidgetRegistry::translatableKeys('list'));
    }

    public function test_marker_icon_is_sanitized_on_save(): void
    {
        $this->assertSame(self::ICON, $this->normalizedSettings(['marker_icon' => self::ICON])['marker_icon']);

        $unsafe = $this->normalizedSettings([
            'marker_icon' => ['library' => 'lucide', 'name' => 'x', 'body' => '<script>alert(1)</script>'],
        ]);
        $this->assertSame('', $unsafe['marker_icon']);
    }

    public function test_style_document_keeps_list_keys_per_breakpoint_and_dark_colours(): void
    {
        $styles = BuilderStyleDocument::normalize([
            'desktop' => [
                'list_layout' => 'inline',
                'list_align' => 'between',
                'list_gap' => ['value' => 12, 'unit' => 'px'],
                'list_marker' => 'none',
                'list_marker_color' => '#F5A623',
                'list_item_bg' => '#eef2ff',
                'list_item_padding_x' => ['value' => 10, 'unit' => 'px'],
                'list_item_radius' => ['value' => 999, 'unit' => 'px'],
                'list_item_hover_effect' => 'lift',
                'list_item_hover_shadow' => ['color' => '#00000033', 'h' => 0, 'v' => 4, 'blur' => 8, 'spread' => 0],
            ],
            'mobile' => ['list_layout' => 'stacked'],
            'dark' => ['list_item_bg' => '#1e293b', 'list_gap' => ['value' => 2, 'unit' => 'px']],
        ]);

        $desktop = $styles['desktop'];
        $this->assertSame('inline', $desktop['list_layout']);
        $this->assertSame('between', $desktop['list_align']);
        $this->assertSame(['value' => 12.0, 'unit' => 'px'], $desktop['list_gap']);
        $this->assertSame('none', $desktop['list_marker']);
        $this->assertSame('#f5a623', $desktop['list_marker_color']);
        $this->assertSame('lift', $desktop['list_item_hover_effect']);
        $this->assertArrayHasKey('list_item_hover_shadow', $desktop);
        $this->assertSame('stacked', $styles['mobile']['list_layout']);
        $this->assertSame(['list_item_bg' => '#1e293b'], $styles['dark']);
    }

    public function test_style_document_drops_unknown_list_values(): void
    {
        $styles = BuilderStyleDocument::normalize([
            'desktop' => [
                'list_layout' => 'grid',
                'list_marker' => 'url(evil)',
                'list_item_hover_effect' => 'spin',
                'list_item_color' => 'url(javascript:alert(1))',
                'list_align' => 'center',
            ],
        ]);

        $this->assertSame(['list_align' => 'center'], $styles['desktop']);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function normalizedSettings(array $settings): array
    {
        $sections = PageLayoutDocument::normalizeSectionsForPages([['type' => 'list', 'settings' => $settings]]);

        return $sections[0]['rows'][0]['columns'][0]['blocks'][0]['settings'];
    }
}
