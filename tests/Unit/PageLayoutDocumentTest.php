<?php

namespace Tests\Unit;

use App\Services\Appearance\PageLayoutDocument;
use Tests\TestCase;

class PageLayoutDocumentTest extends TestCase
{
    public function test_wraps_legacy_flat_section_into_layout_band(): void
    {
        $out = PageLayoutDocument::normalizeSectionsForPages([
            [
                'type' => 'cta',
                'enabled' => true,
                'layout_width' => 'boxed',
                'settings' => ['title' => 'Hi'],
            ],
        ]);

        $this->assertCount(1, $out);
        $this->assertSame('layout', $out[0]['type']);
        $this->assertSame('boxed', $out[0]['layout_width']);
        $this->assertSame('cta', $out[0]['rows'][0]['columns'][0]['blocks'][0]['type']);
        $this->assertSame(
            ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
            $out[0]['rows'][0]['columns'][0]['span'],
        );
    }

    public function test_normalizes_layout_tree_and_clamps_spans(): void
    {
        $out = PageLayoutDocument::normalizeSectionsForPages([
            [
                'type' => 'layout',
                'layout_width' => 'full',
                'rows' => [
                    [
                        'stack_below' => 'tablet',
                        'gap' => 99,
                        'columns' => [
                            [
                                'span' => ['mobile' => 0, 'tablet' => 20, 'desktop' => 6],
                                'blocks' => [
                                    ['type' => 'cta', 'settings' => ['title' => 'A']],
                                ],
                            ],
                            [
                                'span' => 4,
                                'blocks' => [
                                    ['type' => 'services_teaser', 'settings' => []],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $out);
        $col0 = $out[0]['rows'][0]['columns'][0];
        $this->assertSame(['mobile' => 1, 'tablet' => 12, 'desktop' => 6], $col0['span']);
        $this->assertSame(16, $out[0]['rows'][0]['gap']);
        $this->assertSame('tablet', $out[0]['rows'][0]['stack_below']);
        $col1 = $out[0]['rows'][0]['columns'][1];
        $this->assertSame(['mobile' => 4, 'tablet' => 4, 'desktop' => 4], $col1['span']);
    }

    public function test_present_public_keeps_structure_and_drops_disabled_blocks(): void
    {
        $normalized = PageLayoutDocument::normalizeSectionsForPages([
            [
                'type' => 'layout',
                'enabled' => true,
                'rows' => [
                    [
                        'columns' => [
                            [
                                'span' => ['mobile' => 12, 'tablet' => 6, 'desktop' => 6],
                                'blocks' => [
                                    ['type' => 'cta', 'enabled' => true, 'settings' => ['title' => 'On']],
                                    ['type' => 'tech_stack', 'enabled' => false, 'settings' => []],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $public = PageLayoutDocument::presentPublicForPages($normalized, 'en');
        $this->assertCount(1, $public);
        $this->assertSame('layout', $public[0]['type']);
        $blocks = $public[0]['rows'][0]['columns'][0]['blocks'];
        $this->assertCount(1, $blocks);
        $this->assertSame('cta', $blocks[0]['type']);
        $this->assertArrayHasKey('settings', $blocks[0]);
    }

    public function test_persists_row_and_column_background_and_styles(): void
    {
        $out = PageLayoutDocument::normalizeSectionsForPages([
            [
                'type' => 'layout',
                'settings' => [
                    'background' => ['type' => 'color', 'color' => '#112233'],
                ],
                'styles' => [
                    'desktop' => ['padding' => ['top' => 8, 'right' => 8, 'bottom' => 8, 'left' => 8, 'unit' => 'px']],
                ],
                'rows' => [
                    [
                        'settings' => [
                            'background' => ['type' => 'gradient', 'gradient_from' => '#0389a1', 'gradient_to' => '#0ea5e9'],
                        ],
                        'styles' => [
                            'desktop' => ['margin' => ['top' => 4, 'right' => 0, 'bottom' => 4, 'left' => 0, 'unit' => 'px']],
                        ],
                        'columns' => [
                            [
                                'span' => 12,
                                'settings' => [
                                    'background' => ['type' => 'particles', 'particles_density' => 40],
                                ],
                                'styles' => [
                                    'desktop' => ['border_style' => 'solid', 'border_width' => ['value' => 1, 'unit' => 'px']],
                                ],
                                'blocks' => [
                                    [
                                        'type' => 'services_teaser',
                                        'settings' => [
                                            'title' => 'Services',
                                            'background' => ['type' => 'color', 'color' => '#abcdef'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $band = $out[0];
        $this->assertSame('color', $band['settings']['background']['type']);
        $this->assertSame('#112233', $band['settings']['background']['color']);
        $this->assertSame(8.0, $band['styles']['desktop']['padding']['top']);

        $row = $band['rows'][0];
        $this->assertSame('gradient', $row['settings']['background']['type']);
        $this->assertSame(4.0, $row['styles']['desktop']['margin']['top']);

        $col = $row['columns'][0];
        $this->assertSame('particles', $col['settings']['background']['type']);
        $this->assertSame(40, $col['settings']['background']['particles_density']);
        $this->assertSame('solid', $col['styles']['desktop']['border_style']);

        $block = $col['blocks'][0];
        $this->assertSame('color', $block['settings']['background']['type']);
        $this->assertSame('#abcdef', $block['settings']['background']['color']);

        $public = PageLayoutDocument::presentPublicForPages($out, 'en');
        $this->assertSame('gradient', $public[0]['rows'][0]['settings']['background']['type']);
        $this->assertSame('particles', $public[0]['rows'][0]['columns'][0]['settings']['background']['type']);
        $this->assertSame(
            'color',
            $public[0]['rows'][0]['columns'][0]['blocks'][0]['settings']['background']['type'],
        );
    }

    public function test_enforces_max_instances_across_tree(): void
    {
        $out = PageLayoutDocument::normalizeSectionsForPages([
            [
                'type' => 'layout',
                'rows' => [
                    [
                        'columns' => [
                            [
                                'span' => 12,
                                'blocks' => [
                                    ['type' => 'hero', 'settings' => []],
                                    ['type' => 'hero', 'settings' => []],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $blocks = $out[0]['rows'][0]['columns'][0]['blocks'];
        $this->assertCount(1, $blocks);
        $this->assertSame('hero', $blocks[0]['type']);
    }
}
