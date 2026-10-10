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
                'tab_align' => 'center',
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
        $this->assertSame('center', $bag['tab_align']);
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

    public function test_drops_unknown_tab_alignment(): void
    {
        $out = BuilderStyleDocument::normalize(['desktop' => ['tab_align' => 'end']]);

        $this->assertArrayNotHasKey('tab_align', $out['desktop'] ?? []);
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

    public function test_keeps_valid_testimonial_keys_and_drops_invalid_ones(): void
    {
        $out = BuilderStyleDocument::normalize([
            'desktop' => [
                'rating_color' => '#F59E0B',
                'rating_size' => ['value' => 20, 'unit' => 'px'],
                'quote_font_style' => 'italic',
                'quote_font_weight' => '500',
                'quote_color' => 'javascript:alert(1)',
                'author_name_transform' => 'uppercase',
                'author_meta_font_weight' => 'heavy',
                'avatar_size' => ['value' => 56, 'unit' => 'px'],
                'avatar_ring_color' => '#e2e8f0',
                'quote_decoration' => 'underline',
            ],
            'mobile' => [
                'quote_font_style' => 'oblique',
            ],
        ]);

        $bag = $out['desktop'];
        $this->assertSame('#f59e0b', $bag['rating_color']);
        $this->assertSame(20.0, $bag['rating_size']['value']);
        $this->assertSame('italic', $bag['quote_font_style']);
        $this->assertSame('500', $bag['quote_font_weight']);
        $this->assertArrayNotHasKey('quote_color', $bag);
        $this->assertSame('uppercase', $bag['author_name_transform']);
        $this->assertArrayNotHasKey('author_meta_font_weight', $bag);
        $this->assertSame(56.0, $bag['avatar_size']['value']);
        $this->assertSame('#e2e8f0', $bag['avatar_ring_color']);
        $this->assertArrayNotHasKey('quote_decoration', $bag);
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

    public function test_icon_color_is_a_colour_key_in_light_and_dark_bags(): void
    {
        $out = BuilderStyleDocument::normalize([
            'desktop' => ['icon_color' => '#0284C7'],
            'mobile' => ['icon_color' => 'red;}body{'],
            'dark' => ['icon_color' => 'var(--kit-color-accent)'],
        ]);

        $this->assertSame('#0284c7', $out['desktop']['icon_color']);
        $this->assertArrayNotHasKey('mobile', $out);
        $this->assertSame('var(--kit-color-accent)', $out['dark']['icon_color']);
    }

    public function test_hero_glow_colours_and_intensity_are_kept_with_dark_colours_only(): void
    {
        $out = BuilderStyleDocument::normalize([
            'desktop' => ['glow_primary_color' => '#38BDF8', 'glow_opacity' => 0.4],
            'mobile' => ['glow_opacity' => 3, 'glow_secondary_color' => 'url(x)'],
            'dark' => [
                'glow_primary_color' => '#0EA5E9',
                'glow_secondary_color' => 'theme',
                'glow_opacity' => 0.1,
            ],
        ]);

        $this->assertSame('#38bdf8', $out['desktop']['glow_primary_color']);
        $this->assertSame(0.4, $out['desktop']['glow_opacity']);
        $this->assertSame(['glow_opacity' => 1.0], $out['mobile']);
        $this->assertSame(['glow_primary_color' => '#0ea5e9', 'glow_secondary_color' => 'theme'], $out['dark']);
    }

    public function test_hero_badge_highlight_and_tech_keys_are_kept_with_dark_colours_only(): void
    {
        $out = BuilderStyleDocument::normalize([
            'desktop' => [
                'badge_color' => '#075985',
                'badge_radius' => ['value' => 8, 'unit' => 'px'],
                'badge_transform' => 'uppercase',
                'highlight_color' => '#0284C7',
                'highlight_color_end' => 'red;}body{',
                'highlight_font_style' => 'italic',
                'tech_icon_size' => ['value' => 32, 'unit' => 'px'],
                'tech_divider_color' => '#e2e8f0',
                'tech_unknown' => '#000000',
            ],
            'dark' => [
                'highlight_color_end' => '#22D3EE',
                'tech_icon_hover_color' => 'theme',
                'badge_font_weight' => '700',
            ],
        ]);

        $bag = $out['desktop'];
        $this->assertSame('#075985', $bag['badge_color']);
        $this->assertSame(8.0, $bag['badge_radius']['value']);
        $this->assertSame('uppercase', $bag['badge_transform']);
        $this->assertSame('#0284c7', $bag['highlight_color']);
        $this->assertArrayNotHasKey('highlight_color_end', $bag);
        $this->assertSame('italic', $bag['highlight_font_style']);
        $this->assertSame(32.0, $bag['tech_icon_size']['value']);
        $this->assertSame('#e2e8f0', $bag['tech_divider_color']);
        $this->assertArrayNotHasKey('tech_unknown', $bag);
        $this->assertSame(['highlight_color_end' => '#22d3ee', 'tech_icon_hover_color' => 'theme'], $out['dark']);
    }

    public function test_dark_bag_keeps_only_colour_and_shadow_keys(): void
    {
        $out = BuilderStyleDocument::normalize([
            'dark' => [
                'text_color' => '#F1F5F9',
                'card_bg' => 'rgba(15, 23, 42, 0.9)',
                'btn_primary_hover_bg' => '#0ea5e9',
                'box_shadow' => ['color' => '#00000099', 'h' => 0, 'v' => 4, 'blur' => 12],
                'card_hover_shadow' => ['color' => '#000', 'blur' => 8],
                'font_size' => ['value' => 20, 'unit' => 'px'],
                'card_radius' => ['value' => 8, 'unit' => 'px'],
                'btn_primary_font_size' => ['value' => 16, 'unit' => 'px'],
                'custom_css' => 'color:red',
                'unknown' => '#fff',
            ],
        ]);

        $dark = $out['dark'];
        $this->assertSame('#f1f5f9', $dark['text_color']);
        $this->assertSame('rgba(15, 23, 42, 0.9)', $dark['card_bg']);
        $this->assertSame('#0ea5e9', $dark['btn_primary_hover_bg']);
        $this->assertSame(12.0, $dark['box_shadow']['blur']);
        $this->assertSame(8.0, $dark['card_hover_shadow']['blur']);
        foreach (['font_size', 'card_radius', 'btn_primary_font_size', 'custom_css', 'unknown'] as $key) {
            $this->assertArrayNotHasKey($key, $dark);
        }
    }

    public function test_dark_bag_accepts_theme_sentinel_and_rejects_unsafe_values(): void
    {
        $out = BuilderStyleDocument::normalize([
            'desktop' => ['text_color' => '#111111'],
            'dark' => [
                'text_color' => 'theme',
                'box_shadow' => 'theme',
                'title_color' => 'red;}</style><script>',
                'link_color' => 'expression(alert(1))',
                'card_bg' => 'url(javascript:alert(1))',
            ],
        ]);

        $this->assertSame('#111111', $out['desktop']['text_color']);
        $this->assertSame(['text_color' => 'theme', 'box_shadow' => 'theme'], $out['dark']);
    }

    public function test_empty_dark_bag_is_omitted(): void
    {
        $out = BuilderStyleDocument::normalize([
            'desktop' => ['object_fit' => 'cover'],
            'dark' => ['font_size' => '12px'],
        ]);

        $this->assertArrayNotHasKey('dark', $out);
    }

    public function test_light_bags_do_not_accept_theme_sentinel(): void
    {
        $out = BuilderStyleDocument::normalize(['desktop' => ['text_color' => 'theme']]);

        $this->assertNull($out);
    }

    public function test_colours_accept_design_kit_variables_only_in_strict_form(): void
    {
        $out = BuilderStyleDocument::normalize([
            'desktop' => [
                'text_color' => 'var(--kit-color-primary)',
                'title_color' => 'var(--kit-color-primary, red)',
                'link_color' => 'var(--other)',
            ],
            'dark' => ['text_color' => 'var(--kit-color-surface-2)'],
        ]);

        $this->assertSame(['text_color' => 'var(--kit-color-primary)'], $out['desktop']);
        $this->assertSame('var(--kit-color-surface-2)', $out['dark']['text_color']);
    }

    public function test_page_layout_keeps_block_dark_styles(): void
    {
        $sections = PageLayoutDocument::normalizeSectionsForPages([
            [
                'type' => 'layout',
                'rows' => [[
                    'columns' => [[
                        'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                        'blocks' => [[
                            'id' => 'blk_image_2',
                            'type' => 'image',
                            'kind' => 'widget',
                            'styles' => ['dark' => ['border_color' => '#e2e8f0', 'width' => '40px']],
                            'settings' => ['alt' => 'x'],
                        ]],
                    ]],
                ]],
            ],
        ]);

        $block = $sections[0]['rows'][0]['columns'][0]['blocks'][0];
        $this->assertSame(['border_color' => '#e2e8f0'], $block['styles']['dark']);
    }
}
