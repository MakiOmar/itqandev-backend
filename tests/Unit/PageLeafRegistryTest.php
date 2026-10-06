<?php

namespace Tests\Unit;

use App\Services\Appearance\PageLayoutDocument;
use App\Services\Appearance\PageLeafRegistry;
use App\Services\Appearance\WidgetRegistry;
use App\Services\Appearance\KitRegistry;
use PHPUnit\Framework\TestCase;

class PageLeafRegistryTest extends TestCase
{
    public function test_infers_kit_kind_for_legacy_hero(): void
    {
        $this->assertSame(PageLeafRegistry::KIND_KIT, PageLeafRegistry::inferKind('hero'));
        $this->assertTrue(KitRegistry::has('hero'));
    }

    public function test_infers_widget_kind_for_heading(): void
    {
        $this->assertSame(PageLeafRegistry::KIND_WIDGET, PageLeafRegistry::inferKind('heading'));
        $this->assertTrue(WidgetRegistry::has('heading'));
    }

    public function test_testimonial_list_is_a_widget_distinct_from_the_testimonials_kit(): void
    {
        $this->assertSame(PageLeafRegistry::KIND_WIDGET, PageLeafRegistry::inferKind('testimonial_list'));
        $this->assertSame(PageLeafRegistry::KIND_KIT, PageLeafRegistry::inferKind('testimonials'));

        $defaults = WidgetRegistry::defaultSettings('testimonial_list');
        $this->assertFalse($defaults['carousel']);
        $this->assertSame(['mobile' => 1, 'tablet' => 2, 'desktop' => 3], $defaults['columns']);
        $this->assertSame('', $defaults['title']);
        $this->assertSame('sides', $defaults['arrows_position']);
        $arrows = collect(WidgetRegistry::forAdmin())->firstWhere('type', 'testimonial_list')['settings_fields'];
        $arrowsField = collect($arrows)->firstWhere('key', 'arrows_position');
        $this->assertCount(9, $arrowsField['options']);
        $this->assertSame(['title', 'subtitle'], WidgetRegistry::translatableKeys('testimonial_list'));

        $admin = collect(WidgetRegistry::forAdmin())->firstWhere('type', 'testimonial_list');
        $this->assertNotNull($admin);
        $this->assertSame('Content', $admin['category']);
        $this->assertContains('responsive_columns', array_column($admin['settings_fields'], 'type'));
    }

    public function test_testimonials_kit_offers_carousel_layout_settings(): void
    {
        $defaults = KitRegistry::defaultSettings('testimonials');
        $this->assertFalse($defaults['carousel']);
        $this->assertSame('sides', $defaults['arrows_position']);
        $this->assertFalse($defaults['autoplay']);

        $kit = collect(KitRegistry::forAdmin())->firstWhere('type', 'testimonials');
        $carousel = collect($kit['settings_fields'])->firstWhere('key', 'carousel');
        $this->assertSame('boolean', $carousel['type']);
        $keys = array_column($kit['settings_fields'], 'key');
        foreach (['carousel', 'autoplay', 'autoplay_seconds', 'arrows_position'] as $key) {
            $this->assertContains($key, $keys);
        }
        $this->assertSame(['title', 'subtitle'], KitRegistry::translatableKeys('testimonials'));
    }

    public function test_case_studies_kit_offers_card_style_select(): void
    {
        $this->assertSame('overlay', KitRegistry::defaultSettings('case_studies')['card_style']);

        $kit = collect(KitRegistry::forAdmin())->firstWhere('type', 'case_studies');
        $field = collect($kit['settings_fields'])->firstWhere('key', 'card_style');
        $this->assertSame('select', $field['type']);
        $this->assertSame(['overlay', 'detailed'], array_column($field['options'], 'value'));
        $this->assertNotContains('card_style', KitRegistry::translatableKeys('case_studies'));
    }

    public function test_projects_list_kit_offers_same_card_style_select_as_case_studies(): void
    {
        $this->assertSame('overlay', KitRegistry::defaultSettings('projects_list')['card_style']);

        $kits = collect(KitRegistry::forAdmin());
        $field = collect($kits->firstWhere('type', 'projects_list')['settings_fields'])->firstWhere('key', 'card_style');
        $caseStudiesField = collect($kits->firstWhere('type', 'case_studies')['settings_fields'])->firstWhere('key', 'card_style');

        $this->assertSame($caseStudiesField, $field);
        $this->assertNotContains('card_style', KitRegistry::translatableKeys('projects_list'));
    }

    public function test_hero_kit_offers_watermark_motion_off_by_default(): void
    {
        $defaults = KitRegistry::defaultSettings('hero');
        $this->assertFalse($defaults['watermark_motion']);
        $this->assertSame(40, $defaults['watermark_speed']);

        $kit = collect(KitRegistry::forAdmin())->firstWhere('type', 'hero');
        $fields = collect($kit['settings_fields'])->keyBy('key');
        $this->assertSame('boolean', $fields['watermark_motion']['type']);
        $this->assertSame([10, 120, 40], [$fields['watermark_speed']['min'], $fields['watermark_speed']['max'], $fields['watermark_speed']['default']]);
        $this->assertNotContains('watermark_motion', KitRegistry::translatableKeys('hero'));
        $this->assertNotContains('watermark_speed', KitRegistry::translatableKeys('hero'));
    }

    public function test_hero_kit_fields_are_grouped_with_valid_toggle_dependencies(): void
    {
        $kit = collect(KitRegistry::forAdmin())->firstWhere('type', 'hero');
        $fields = collect($kit['settings_fields'])->keyBy('key');

        $this->assertSame(
            ['content', 'images', 'layout', 'watermark', 'particles', 'floating_icons'],
            $fields->pluck('group')->unique()->values()->all(),
        );
        foreach ($fields->whereNotNull('show_if') as $key => $field) {
            $parent = $fields->get($field['show_if']);
            $this->assertNotNull($parent, "{$key} depends on a missing field");
            $this->assertSame('boolean', $parent['type'], "{$key} must depend on a boolean toggle");
            $this->assertSame($field['group'], $parent['group'], "{$key} and its toggle must share a group");
        }
        $this->assertSame('particles_enabled', $fields['particles_density']['show_if']);
        $this->assertSame('floating_icons_enabled', $fields['floating_icons']['show_if']);
    }

    public function test_page_layout_normalize_adds_kind_to_legacy_blocks(): void
    {
        $normalized = PageLayoutDocument::normalizeSectionsForPages([
            [
                'type' => 'layout',
                'enabled' => true,
                'layout_width' => 'boxed',
                'rows' => [[
                    'columns' => [[
                        'span' => 12,
                        'blocks' => [
                            ['type' => 'cta', 'settings' => ['title' => 'Hi']],
                            ['kind' => 'widget', 'type' => 'heading', 'settings' => ['text' => 'Hello', 'level' => 'h2']],
                        ],
                    ]],
                ]],
            ],
        ]);

        $this->assertNotEmpty($normalized);
        $blocks = $normalized[0]['rows'][0]['columns'][0]['blocks'];
        $this->assertSame('kit', $blocks[0]['kind']);
        $this->assertSame('cta', $blocks[0]['type']);
        $this->assertSame('widget', $blocks[1]['kind']);
        $this->assertSame('heading', $blocks[1]['type']);
    }
}
