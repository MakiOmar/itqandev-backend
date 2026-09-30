<?php

namespace Tests\Unit;

use App\Services\Appearance\BuilderStyleDocument;
use App\Services\Appearance\PageLayoutDocument;
use Tests\TestCase;

class BuilderStyleDocumentTest extends TestCase
{
    public function test_drops_unknown_keys(): void
    {
        $out = BuilderStyleDocument::normalize([
            'desktop' => [
                'object_fit' => 'cover',
                'hack' => 'alert(1)',
            ],
        ]);

        $this->assertIsArray($out);
        $this->assertSame('cover', $out['desktop']['object_fit']);
        $this->assertArrayNotHasKey('hack', $out['desktop']);
    }

    public function test_omits_empty_breakpoint_bags(): void
    {
        $out = BuilderStyleDocument::normalize([
            'desktop' => ['object_fit' => 'contain'],
            'tablet' => [],
            'mobile' => ['not_a_key' => 1],
        ]);

        $this->assertArrayHasKey('desktop', $out);
        $this->assertArrayNotHasKey('tablet', $out);
        $this->assertArrayNotHasKey('mobile', $out);
    }

    public function test_keeps_valid_widget_part_keys_and_drops_invalid_ones(): void
    {
        $out = BuilderStyleDocument::normalize([
            'desktop' => [
                'tab_active_color' => '#ef4444',
                'tab_bg' => 'url(javascript:alert(1))',
                'tab_font_weight' => 700,
                'nav_size' => ['value' => 48, 'unit' => 'px'],
                'nav_hover_bg' => '#0f172a',
                'link_transform' => 'uppercase',
                'link_font_weight' => 'heavy',
                'link_letter_spacing' => ['value' => 0.1, 'unit' => 'em'],
                'title_color' => '#f59e0b',
                'title_font_size' => ['value' => 48, 'unit' => 'px'],
                'subtitle_transform' => 'shout',
                'subtitle_color' => 'expression(alert(1))',
            ],
        ]);

        $bag = $out['desktop'];
        $this->assertSame('#ef4444', $bag['tab_active_color']);
        $this->assertArrayNotHasKey('tab_bg', $bag);
        $this->assertSame('700', $bag['tab_font_weight']);
        $this->assertSame(48.0, $bag['nav_size']['value']);
        $this->assertSame('#0f172a', $bag['nav_hover_bg']);
        $this->assertSame('uppercase', $bag['link_transform']);
        $this->assertArrayNotHasKey('link_font_weight', $bag);
        $this->assertSame('em', $bag['link_letter_spacing']['unit']);
        $this->assertSame('#f59e0b', $bag['title_color']);
        $this->assertSame(48.0, $bag['title_font_size']['value']);
        $this->assertArrayNotHasKey('subtitle_transform', $bag);
        $this->assertArrayNotHasKey('subtitle_color', $bag);
    }

    public function test_keeps_valid_button_part_keys_and_drops_invalid_ones(): void
    {
        $out = BuilderStyleDocument::normalize([
            'desktop' => [
                'btn_primary_bg' => '#f59e0b',
                'btn_primary_hover_bg' => 'red; background:url(x)',
                'btn_primary_radius' => ['value' => 999, 'unit' => 'px'],
                'btn_primary_font_weight' => '600',
                'btn_secondary_shadow' => ['h' => 0, 'v' => 4, 'blur' => 12, 'spread' => 0, 'color' => '#00000033'],
                'btn_secondary_transform' => 'sideways',
                'btn_tertiary_bg' => '#000000',
            ],
        ]);

        $bag = $out['desktop'];
        $this->assertSame('#f59e0b', $bag['btn_primary_bg']);
        $this->assertArrayNotHasKey('btn_primary_hover_bg', $bag);
        $this->assertSame(999.0, $bag['btn_primary_radius']['value']);
        $this->assertSame('600', $bag['btn_primary_font_weight']);
        $this->assertSame(12.0, $bag['btn_secondary_shadow']['blur']);
        $this->assertArrayNotHasKey('btn_secondary_transform', $bag);
        $this->assertArrayNotHasKey('btn_tertiary_bg', $bag);
    }

    public function test_keeps_valid_case_study_card_keys_and_drops_invalid_ones(): void
    {
        $out = BuilderStyleDocument::normalize([
            'mobile' => [
                'card_bg' => '#0F172A',
                'card_border_color' => 'blue',
                'card_radius' => ['value' => 24, 'unit' => 'px'],
                'card_hover_shadow' => ['h' => 0, 'v' => 8, 'blur' => 24, 'spread' => 0, 'color' => '#6366f133'],
                'card_cat_font_weight' => '700',
                'card_title_transform' => 'uppercase',
                'card_summary_line_height' => 'tall',
                'card_chip_bg' => 'rgba(30, 41, 59, 0.8)',
                'btn_card_hover_bg' => '#4f46e5',
                'btn_card_padding_y' => ['value' => 14, 'unit' => 'px'],
                'card_unknown' => '#ffffff',
            ],
        ]);

        $bag = $out['mobile'];
        $this->assertSame('#0f172a', $bag['card_bg']);
        $this->assertArrayNotHasKey('card_border_color', $bag);
        $this->assertSame(24.0, $bag['card_radius']['value']);
        $this->assertSame(24.0, $bag['card_hover_shadow']['blur']);
        $this->assertSame('700', $bag['card_cat_font_weight']);
        $this->assertSame('uppercase', $bag['card_title_transform']);
        $this->assertArrayNotHasKey('card_summary_line_height', $bag);
        $this->assertSame('rgba(30, 41, 59, 0.8)', $bag['card_chip_bg']);
        $this->assertSame('#4f46e5', $bag['btn_card_hover_bg']);
        $this->assertSame(14.0, $bag['btn_card_padding_y']['value']);
        $this->assertArrayNotHasKey('card_unknown', $bag);
    }

    public function test_strips_unsafe_custom_css(): void
    {
        $out = BuilderStyleDocument::normalize([
            'desktop' => [
                'custom_css' => '@import url("https://evil.example"); selector { color: red; } expression(alert(1)) javascript:void(0) </style>',
            ],
        ]);

        $css = $out['desktop']['custom_css'];
        $this->assertStringNotContainsString('@import', $css);
        $this->assertStringNotContainsString('expression(', $css);
        $this->assertStringNotContainsString('javascript:', $css);
        $this->assertStringNotContainsString('</style>', $css);
        $this->assertStringContainsString('selector', $css);
        $this->assertStringContainsString('color: red', $css);
    }

    public function test_page_normalize_keeps_hide_on_and_styles(): void
    {
        $sections = PageLayoutDocument::normalizeSectionsForPages([
            [
                'type' => 'layout',
                'layout_width' => 'boxed',
                'hide_on' => ['mobile' => true],
                'rows' => [[
                    'hide_on' => ['tablet' => true],
                    'columns' => [[
                        'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                        'blocks' => [[
                            'id' => 'blk_image_1',
                            'type' => 'image',
                            'kind' => 'widget',
                            'hide_on' => ['desktop' => true],
                            'styles' => [
                                'desktop' => [
                                    'object_fit' => 'contain',
                                    'evil' => 1,
                                    'width' => ['value' => 320, 'unit' => 'px'],
                                ],
                            ],
                            'settings' => ['alt' => 'x'],
                        ]],
                    ]],
                ]],
            ],
        ]);

        $this->assertCount(1, $sections);
        $band = $sections[0];
        $this->assertTrue($band['hide_on']['mobile']);
        $row = $band['rows'][0];
        $this->assertTrue($row['hide_on']['tablet']);
        $block = $row['columns'][0]['blocks'][0];
        $this->assertTrue($block['hide_on']['desktop']);
        $this->assertSame('contain', $block['styles']['desktop']['object_fit']);
        $this->assertSame(320.0, $block['styles']['desktop']['width']['value']);
        $this->assertArrayNotHasKey('evil', $block['styles']['desktop']);
    }
}
