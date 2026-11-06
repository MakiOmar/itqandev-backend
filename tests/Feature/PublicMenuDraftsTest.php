<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Services\PublicMenuResolver;
use App\Support\ProjectSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CountsTableQueries;
use Tests\TestCase;

class PublicMenuDraftsTest extends TestCase
{
    use CountsTableQueries, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ProjectSettingsStore::save([
            'default_locale' => 'en',
            'site_languages' => [
                ['code' => 'en', 'label' => 'English', 'native_label' => 'English', 'rtl' => false],
            ],
        ]);
    }

    public function test_unpublished_references_are_left_out_of_public_menus(): void
    {
        $project = Project::query()->create(['title' => 'Secret project', 'slug' => 'secret-project', 'status' => 'draft']);
        $post = BlogPost::query()->create([
            'title' => 'Secret post',
            'slug' => 'secret-post',
            'status' => 'draft',
            'author_id' => User::factory()->create()->id,
        ]);
        $service = Service::factory()->create(['name' => 'Secret service', 'slug' => 'secret-service', 'is_published' => false]);
        $live = Project::query()->create(['title' => 'Live project', 'slug' => 'live-project', 'status' => 'published']);

        $menu = Menu::create(['name' => 'Primary', 'slug' => 'drafts-test']);
        foreach ([
            [MenuItem::TYPE_PROJECT, $project->id],
            [MenuItem::TYPE_BLOG_POST, $post->id],
            [MenuItem::TYPE_SERVICE, $service->id],
            [MenuItem::TYPE_PROJECT, $live->id],
        ] as $order => [$type, $id]) {
            $this->menuItem($menu, $type, $id, $order);
        }

        $tree = PublicMenuResolver::resolvePublishedTree('drafts-test', 'en');

        $this->assertSame(['Live project'], array_column($tree, 'label'));
    }

    public function test_draft_parent_slug_is_not_in_menu_links(): void
    {
        $parent = Page::create(['title' => 'Hidden', 'slug' => 'hidden-parent', 'status' => Page::STATUS_DRAFT, 'sections' => []]);
        $child = Page::create([
            'title' => 'Child',
            'slug' => 'child-page',
            'parent_id' => $parent->id,
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
            'sections' => [],
        ]);
        $menu = Menu::create(['name' => 'Primary', 'slug' => 'nested-test']);
        $this->menuItem($menu, MenuItem::TYPE_PAGE, $child->id, 0);

        $tree = PublicMenuResolver::resolvePublishedTree('nested-test', 'en');

        $this->assertSame('/en/pages/child-page/', $tree[0]['href']);
    }

    public function test_menu_item_images_load_in_one_query(): void
    {
        Storage::fake('public');
        $owner = BlogPost::query()->create([
            'title' => 'Owner',
            'slug' => 'owner',
            'status' => 'published',
            'author_id' => User::factory()->create()->id,
        ]);
        $menu = Menu::create(['name' => 'Mega', 'slug' => 'mega-test']);
        foreach (range(1, 3) as $n) {
            $media = $owner->addMediaFromString('img')->usingFileName("item-{$n}.txt")->toMediaCollection('gallery');
            MenuItem::create([
                'menu_id' => $menu->id,
                'sort_order' => $n,
                'label' => "Item {$n}",
                'item_type' => MenuItem::TYPE_CUSTOM_LINK,
                'url' => "/item-{$n}/",
                'image_id' => $media->id,
                'open_in_new_tab' => false,
            ]);
        }

        $tree = null;
        $queries = $this->queriesAgainst('media', function () use (&$tree): void {
            $tree = PublicMenuResolver::resolvePublishedTree('mega-test', 'en');
        });

        $this->assertSame(1, $queries);
        $this->assertStringContainsString('item-3.txt', (string) $tree[2]['image_url']);
    }

    private function menuItem(Menu $menu, string $type, int $referenceId, int $order): void
    {
        MenuItem::create([
            'menu_id' => $menu->id,
            'sort_order' => $order,
            'label' => null,
            'item_type' => $type,
            'reference_id' => $referenceId,
            'open_in_new_tab' => false,
        ]);
    }
}
