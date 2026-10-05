<?php

namespace Tests\Feature;

use App\Models\BuilderGlobal;
use App\Services\Appearance\GlobalWidgetService;
use App\Services\Appearance\PageLayoutDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalWidgetPlacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_placement_keeps_only_the_link_and_visibility(): void
    {
        $sections = PageLayoutDocument::normalizeSectionsForPages([[
            'type' => 'layout',
            'rows' => [['columns' => [['blocks' => [[
                'id' => 'glb_place',
                'kind' => 'global',
                'global_id' => 7,
                'type' => 'heading',
                'enabled' => false,
                'hide_on' => ['mobile' => true],
                'settings' => ['text' => 'Cached copy'],
                'styles' => ['desktop' => ['color' => '#ff0000']],
            ]]]]]],
        ]]);

        $block = $this->firstBlock($sections);
        $this->assertSame('global', $block['kind']);
        $this->assertSame('global', $block['type']);
        $this->assertSame(7, $block['global_id']);
        $this->assertFalse($block['enabled']);
        $this->assertSame([], $block['settings']);
        $this->assertArrayNotHasKey('styles', $block);
        $this->assertTrue($block['hide_on']['mobile']);
    }

    public function test_resolve_uses_global_content_with_placement_visibility(): void
    {
        $global = BuilderGlobal::query()->create([
            'name' => 'Promo heading',
            'slug' => 'promo-heading',
            'status' => BuilderGlobal::STATUS_PUBLISHED,
            'document' => [
                'kind' => 'widget',
                'type' => 'heading',
                'enabled' => true,
                'settings' => ['text' => 'Shared'],
                'styles' => ['desktop' => ['color' => '#00ff00']],
            ],
        ]);

        $out = GlobalWidgetService::resolveSections([[
            'type' => 'layout',
            'rows' => [['columns' => [['blocks' => [[
                'id' => 'glb_place',
                'kind' => 'global',
                'global_id' => $global->id,
                'enabled' => false,
                'hide_on' => ['tablet' => true],
            ]]]]]],
        ]]);

        $block = $this->firstBlock($out);
        $this->assertSame('glb_place', $block['id']);
        $this->assertSame('heading', $block['type']);
        $this->assertSame('Shared', $block['settings']['text']);
        $this->assertSame('#00ff00', $block['styles']['desktop']['color']);
        $this->assertSame($global->id, $block['global_source_id']);
        $this->assertFalse($block['enabled']);
        $this->assertTrue($block['hide_on']['tablet']);
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    private function firstBlock(array $sections): array
    {
        return $sections[0]['rows'][0]['columns'][0]['blocks'][0];
    }
}
