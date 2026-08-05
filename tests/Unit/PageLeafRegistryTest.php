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
