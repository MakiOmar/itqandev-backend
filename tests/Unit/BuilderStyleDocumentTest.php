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
