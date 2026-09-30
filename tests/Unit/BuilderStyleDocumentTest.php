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
