<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuilderLinkSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** @return array<string, string> */
    private function adminHeaders(): array
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->firstOrFail();

        return ['Authorization' => 'Bearer '.$admin->createToken('test')->plainTextToken];
    }

    /** @return list<array<string, mixed>> */
    private function search(array $query): array
    {
        return $this->withHeaders($this->adminHeaders())
            ->getJson('/api/appearance/link-search?'.http_build_query($query))
            ->assertOk()
            ->json('data');
    }

    public function test_returns_canonical_paths_for_projects_and_pages(): void
    {
        $project = Project::query()->create(['title' => 'Zebra shop', 'slug' => 'zebra-shop', 'status' => 'published']);
        $parent = Page::query()->create(['title' => 'Zebra parent', 'slug' => 'zebra-parent', 'status' => Page::STATUS_PUBLISHED]);
        $child = Page::query()->create([
            'title' => 'Zebra child', 'slug' => 'zebra-child', 'parent_id' => $parent->id, 'status' => Page::STATUS_PUBLISHED,
        ]);

        $rows = collect($this->search(['q' => 'zebra']));

        $this->assertSame('/portfolio/zebra-shop/', $rows->firstWhere('id', $project->id)['path']);
        $this->assertSame('project', $rows->firstWhere('id', $project->id)['type']);
        $pages = $rows->where('type', 'page')->keyBy('id');
        $this->assertSame('/pages/zebra-parent/', $pages[$parent->id]['path']);
        $this->assertSame('/pages/zebra-parent/zebra-child/', $pages[$child->id]['path']);
    }

    public function test_pretty_page_slugs_keep_marketing_paths(): void
    {
        Page::query()->updateOrCreate(['slug' => 'contact'], ['title' => 'Contact', 'status' => Page::STATUS_PUBLISHED]);

        $rows = collect($this->search(['q' => 'contact', 'type' => 'page']));

        $this->assertContains('/contact/', $rows->pluck('path')->all());
        $this->assertSame(['page'], $rows->pluck('type')->unique()->values()->all());
    }

    public function test_excludes_drafts_and_escapes_wildcards(): void
    {
        Project::query()->create(['title' => 'Hidden draft', 'slug' => 'hidden-draft', 'status' => 'draft']);

        $this->assertSame([], $this->search(['q' => 'hidden draft']));
        $this->assertSame([], $this->search(['q' => '%_%']));
    }

    public function test_disabled_module_is_not_searched(): void
    {
        Project::query()->create(['title' => 'Module off', 'slug' => 'module-off', 'status' => 'published']);
        config(['features.modules.projects' => false]);

        $this->assertSame([], $this->search(['q' => 'module off']));
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/appearance/link-search?type=project')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_validation_rejects_long_query_and_unknown_type(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/appearance/link-search?'.http_build_query(['q' => str_repeat('a', 101), 'type' => 'nope']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q', 'type']);
    }

    public function test_forbidden_for_editor(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('editor');

        $this->withHeaders(['Authorization' => 'Bearer '.$editor->createToken('test')->plainTextToken])
            ->getJson('/api/appearance/link-search')
            ->assertForbidden();
    }
}
