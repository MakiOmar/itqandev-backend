<?php

namespace Tests\Unit;

use App\Services\Appearance\BuilderCssCompiler;
use App\Services\Appearance\LayoutNodeAttributes;
use App\Services\Appearance\PageLayoutDocument;
use Tests\TestCase;

class LayoutNodeAttributesTest extends TestCase
{
    public function test_css_id_must_be_a_plain_identifier(): void
    {
        $this->assertSame('pricing', LayoutNodeAttributes::normalizeId('  #pricing '));
        $this->assertSame('a_b-1', LayoutNodeAttributes::normalizeId('a_b-1'));
        $this->assertSame('', LayoutNodeAttributes::normalizeId('1st'));
        $this->assertSame('', LayoutNodeAttributes::normalizeId('a b'));
        $this->assertSame('', LayoutNodeAttributes::normalizeId('x"><script>'));
        $this->assertSame('', LayoutNodeAttributes::normalizeId(str_repeat('a', 65)));
        $this->assertSame('', LayoutNodeAttributes::normalizeId(['a']));
    }

    public function test_css_classes_drop_invalid_names_duplicates_and_extras(): void
    {
        $this->assertSame('promo -dark _x', LayoutNodeAttributes::normalizeClasses(' .promo  -dark promo _x 9bad a{b} '));
        $many = implode(' ', array_map(static fn (int $i) => 'c'.$i, range(1, 15)));
        $this->assertCount(LayoutNodeAttributes::MAX_CLASSES, explode(' ', LayoutNodeAttributes::normalizeClasses($many)));
        $this->assertSame('', LayoutNodeAttributes::normalizeClasses(null));
    }

    public function test_layout_nodes_keep_valid_attributes_when_saved_and_presented(): void
    {
        $sections = PageLayoutDocument::normalizeSectionsForPages([[
            'type' => 'layout',
            'css_id' => 'hero-band',
            'css_classes' => 'band dark',
            'rows' => [[
                'css_id' => 'bad id',
                'css_classes' => 'row-a',
                'columns' => [[
                    'css_id' => 'col-a',
                    'blocks' => [
                        ['type' => 'heading', 'settings' => ['text' => 'Hi'], 'css_id' => '#contact', 'css_classes' => '.cta'],
                        [
                            'type' => 'inner_band',
                            'css_id' => 'inner',
                            'rows' => [['columns' => [['blocks' => []]]]],
                        ],
                    ],
                ]],
            ]],
        ]]);

        $band = $sections[0];
        $row = $band['rows'][0];
        $column = $row['columns'][0];
        $this->assertSame('hero-band', $band['css_id']);
        $this->assertSame('band dark', $band['css_classes']);
        $this->assertArrayNotHasKey('css_id', $row);
        $this->assertSame('row-a', $row['css_classes']);
        $this->assertSame('col-a', $column['css_id']);
        $this->assertSame('contact', $column['blocks'][0]['css_id']);
        $this->assertSame('cta', $column['blocks'][0]['css_classes']);
        $this->assertSame('inner', $column['blocks'][1]['css_id']);

        $presented = PageLayoutDocument::presentPublicForPages($sections, 'en');
        $pBand = $presented[0];
        $pColumn = $pBand['rows'][0]['columns'][0];
        $this->assertSame('hero-band', $pBand['css_id']);
        $this->assertSame('row-a', $pBand['rows'][0]['css_classes']);
        $this->assertSame('col-a', $pColumn['css_id']);
        $this->assertSame('contact', $pColumn['blocks'][0]['css_id']);
        $this->assertSame('inner', $pColumn['blocks'][1]['css_id']);
    }

    public function test_custom_css_is_scoped_to_the_css_id_when_set(): void
    {
        $css = BuilderCssCompiler::compile([
            ['id' => 'blk_1', 'type' => 'heading', 'css_id' => 'promo', 'styles' => ['desktop' => ['custom_css' => 'selector{color:red}']]],
            ['id' => 'blk_2', 'type' => 'heading', 'styles' => ['desktop' => ['custom_css' => 'selector{color:blue}']]],
        ])['css'];

        $this->assertStringContainsString('#promo{color:red}', $css);
        $this->assertStringContainsString('#b-blk_2{color:blue}', $css);
    }
}
