<?php

namespace Tests\Feature;

use App\Models\ChromeLayout;
use App\Models\Project;
use App\Models\ProjectTranslation;
use App\Services\Appearance\ChromeLayoutService;
use App\Services\Appearance\LoopQueryService;
use App\Services\Appearance\PageLayoutDocument;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LoopGridLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::forget('project-settings');
        Project::query()->update(['status' => 'draft']);
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private function layout(string $kind, array $blocks): ChromeLayout
    {
        return app(ChromeLayoutService::class)->create($kind, [
            'name' => 'Loop card '.$kind,
            'status' => ChromeLayout::STATUS_PUBLISHED,
            'sections' => [[
                'id' => 'band_card',
                'type' => PageLayoutDocument::TYPE_LAYOUT,
                'enabled' => true,
                'rows' => [[
                    'id' => 'row_card',
                    'columns' => [[
                        'id' => 'col_card',
                        'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                        'blocks' => $blocks,
                    ]],
                ]],
            ]],
        ]);
    }

    /** @return array<string, mixed> */
    private function heading(string $text): array
    {
        return ['id' => 'blk_title', 'kind' => 'widget', 'type' => 'heading', 'enabled' => true, 'settings' => ['text' => $text]];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function firstBlock(array $item): array
    {
        return $item['layout']['sections'][0]['rows'][0]['columns'][0]['blocks'][0];
    }

    private function project(): Project
    {
        $project = Project::query()->create(['title' => 'Shop', 'slug' => 'loop-shop', 'summary' => 'S', 'status' => 'published']);
        ProjectTranslation::query()->create(['project_id' => $project->id, 'locale' => 'ar', 'title' => 'متجر']);

        return $project;
    }

    public function test_items_carry_loop_item_layout_with_resolved_tags(): void
    {
        $project = $this->project();
        $card = $this->layout(ChromeLayout::KIND_LOOP_ITEM, [$this->heading('{{post.title}} · {{project.categories.count}}')]);

        $items = LoopQueryService::items(['source' => 'projects', 'loop_item_id' => $card->id], 'ar');

        $this->assertCount(1, $items);
        $block = $this->firstBlock($items[0]);
        $this->assertSame('متجر · 0', $block['settings']['text']);
        $this->assertSame('blk_title-r'.$project->id, $block['id']);
        $this->assertSame('band_card-r'.$project->id, $items[0]['layout']['sections'][0]['id']);
        $this->assertSame('/work/loop-shop', $items[0]['url']);
    }

    public function test_items_without_loop_item_have_no_layout(): void
    {
        $this->project();

        $this->assertArrayNotHasKey('layout', LoopQueryService::items(['source' => 'projects'], 'en')[0]);
    }

    public function test_non_loop_item_layout_is_ignored(): void
    {
        $this->project();
        $header = $this->layout(ChromeLayout::KIND_HEADER, [$this->heading('{{post.title}}')]);

        $this->assertArrayNotHasKey('layout', LoopQueryService::items(['source' => 'projects', 'loop_item_id' => $header->id], 'en')[0]);
    }

    public function test_nested_loop_grid_inside_loop_item_renders_nothing(): void
    {
        $this->project();
        $card = $this->layout(ChromeLayout::KIND_LOOP_ITEM, [[
            'id' => 'blk_inner_loop',
            'kind' => 'widget',
            'type' => 'loop_grid',
            'enabled' => true,
            'settings' => ['source' => 'projects'],
        ]]);
        // Self-reference: the inner grid would render this same card again.
        $nested = $card->document['sections'];
        $nested[0]['rows'][0]['columns'][0]['blocks'][0]['settings']['loop_item_id'] = $card->id;
        $card = app(ChromeLayoutService::class)->update($card, ['sections' => $nested]);

        $items = LoopQueryService::items(['source' => 'projects', 'loop_item_id' => $card->id], 'en');

        $this->assertSame([], $this->firstBlock($items[0])['settings']['items']);
    }

    public function test_media_fields_resolve_image_dynamic_tags(): void
    {
        $tags = [
            'post.image' => 'https://example.test/hero.jpg',
            'project.categories.first.image' => 'https://example.test/cat.jpg',
        ];
        $blocks = [
            ['id' => 'blk_img', 'kind' => 'widget', 'type' => 'image', 'enabled' => true, 'settings' => ['image' => '{{post.image}}']],
            [
                'id' => 'blk_gallery',
                'kind' => 'widget',
                'type' => 'gallery',
                'enabled' => true,
                'settings' => ['images' => [['image' => '{{ project.categories.first.image }}', 'alt' => '']]],
            ],
        ];

        $saved = $this->layout(ChromeLayout::KIND_LOOP_ITEM, $blocks)->fresh();
        $savedBlocks = $saved->document['sections'][0]['rows'][0]['columns'][0]['blocks'];
        $this->assertSame('{{post.image}}', $savedBlocks[0]['settings']['image']);

        $sections = PageLayoutDocument::presentPublicForPages([[
            'id' => 'band_media',
            'type' => PageLayoutDocument::TYPE_LAYOUT,
            'enabled' => true,
            'rows' => [[
                'id' => 'row_media',
                'columns' => [[
                    'id' => 'col_media',
                    'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                    'blocks' => $blocks,
                ]],
            ]],
        ]], 'en', $tags);

        $presented = $sections[0]['rows'][0]['columns'][0]['blocks'];
        $this->assertSame('https://example.test/hero.jpg', $presented[0]['settings']['image']);
        $this->assertSame('https://example.test/cat.jpg', $presented[1]['settings']['images'][0]['image']);
    }

    public function test_page_layout_presents_loop_grid_items_with_layouts(): void
    {
        $this->project();
        $card = $this->layout(ChromeLayout::KIND_LOOP_ITEM, [$this->heading('{{post.title}}')]);

        $sections = PageLayoutDocument::presentPublicForPages([[
            'id' => 'band_page',
            'type' => PageLayoutDocument::TYPE_LAYOUT,
            'enabled' => true,
            'rows' => [[
                'id' => 'row_page',
                'columns' => [[
                    'id' => 'col_page',
                    'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                    'blocks' => [[
                        'id' => 'blk_loop',
                        'kind' => 'widget',
                        'type' => 'loop_grid',
                        'enabled' => true,
                        'settings' => ['source' => 'projects', 'loop_item_id' => $card->id],
                    ]],
                ]],
            ]],
        ]], 'en', null);

        $items = $sections[0]['rows'][0]['columns'][0]['blocks'][0]['settings']['items'];
        $this->assertSame('Shop', $this->firstBlock($items[0])['settings']['text']);
    }
}
