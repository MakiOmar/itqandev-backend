<?php

namespace Tests\Feature;

use App\Models\BuilderGlobal;
use App\Services\Appearance\GlobalWidgetService;
use App\Services\Appearance\PageLayoutDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CountsTableQueries;
use Tests\TestCase;

class GlobalWidgetPlacementTest extends TestCase
{
    use CountsTableQueries, RefreshDatabase;

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
                'css_id' => 'promo',
                'css_classes' => 'is-promo',
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
        $this->assertSame('promo', $block['css_id']);
        $this->assertSame('is-promo', $block['css_classes']);
    }

    public function test_resolve_takes_css_id_and_classes_from_the_placement_only(): void
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
                'css_id' => 'shared-id',
                'css_classes' => 'shared',
            ],
        ]);

        $out = GlobalWidgetService::resolveSections([[
            'type' => 'layout',
            'rows' => [['columns' => [
                ['blocks' => [['id' => 'glb_a', 'kind' => 'global', 'global_id' => $global->id, 'css_id' => 'first']]],
                ['blocks' => [['id' => 'glb_b', 'kind' => 'global', 'global_id' => $global->id]]],
            ]]],
        ]]);

        $columns = $out[0]['rows'][0]['columns'];
        $this->assertSame('first', $columns[0]['blocks'][0]['css_id']);
        $this->assertArrayNotHasKey('css_classes', $columns[0]['blocks'][0]);
        $this->assertArrayNotHasKey('css_id', $columns[1]['blocks'][0]);
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

    public function test_resolving_many_placements_loads_globals_in_one_query(): void
    {
        $ids = [];
        foreach (['First', 'Second'] as $text) {
            $ids[] = BuilderGlobal::query()->create([
                'name' => $text,
                'slug' => strtolower($text),
                'status' => BuilderGlobal::STATUS_PUBLISHED,
                'document' => ['kind' => 'widget', 'type' => 'heading', 'settings' => ['text' => $text]],
            ])->id;
        }
        $this->app->forgetScopedInstances();
        $placements = array_map(
            fn (int $i, int $id) => ['id' => 'glb_'.$i, 'kind' => 'global', 'global_id' => $id],
            [0, 1, 2],
            [$ids[0], $ids[1], $ids[0]],
        );

        $out = [];
        $queries = $this->queriesAgainst('builder_globals', function () use ($placements, &$out) {
            $out = GlobalWidgetService::resolveSections([[
                'type' => 'layout',
                'rows' => [['columns' => [['blocks' => $placements]]]],
            ]]);
        });

        $this->assertSame(1, $queries);
        $this->assertSame(['First', 'Second', 'First'], array_map(
            fn (array $block) => $block['settings']['text'],
            $out[0]['rows'][0]['columns'][0]['blocks'],
        ));
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
