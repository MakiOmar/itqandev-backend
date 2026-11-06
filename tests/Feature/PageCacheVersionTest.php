<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Support\CacheKey;
use App\Support\ProjectSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CountsTableQueries;
use Tests\TestCase;

class PageCacheVersionTest extends TestCase
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

    public function test_draft_only_saves_keep_public_page_caches_warm(): void
    {
        $this->publishedPage('about-us');
        $draft = $this->draftPage('work-in-progress');
        $this->getJson('/api/public/pages/about-us')->assertOk();

        $draft->update(['title' => 'Still a draft']);

        $queries = $this->queriesAgainst('pages', fn () => $this->getJson('/api/public/pages/about-us')->assertOk());
        $this->assertSame(0, $queries);
    }

    public function test_publishing_and_unpublishing_invalidate_public_pages(): void
    {
        $page = $this->draftPage('launch');
        $this->getJson('/api/public/pages/launch')->assertNotFound();

        $page->update(['status' => Page::STATUS_PUBLISHED]);
        $this->getJson('/api/public/pages/launch')->assertOk();

        $page->update(['status' => Page::STATUS_DRAFT]);
        $this->getJson('/api/public/pages/launch')->assertNotFound();
    }

    public function test_admin_list_reflects_draft_edits(): void
    {
        $this->actingEditor();
        $draft = $this->draftPage('notes');
        $this->getJson('/api/v1/pages')->assertOk()->assertJsonPath('0.title', 'Notes');

        $this->putJson('/api/v1/pages/'.$draft->id, ['title' => 'Renamed notes'])->assertOk();

        $this->getJson('/api/v1/pages')->assertOk()->assertJsonPath('0.title', 'Renamed notes');
    }

    public function test_version_counter_seeds_and_increments_on_the_database_store(): void
    {
        config(['cache.default' => 'database']);
        Cache::forgetDriver('database');

        CacheKey::incrementVersion('test:version');
        CacheKey::incrementVersion('test:version');

        $this->assertSame(3, (int) Cache::get('test:version'));
    }

    private function publishedPage(string $slug): Page
    {
        return Page::query()->create([
            'title' => ucfirst($slug),
            'slug' => $slug,
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
            'sections' => [],
        ]);
    }

    private function draftPage(string $slug): Page
    {
        return Page::query()->create([
            'title' => ucfirst($slug),
            'slug' => $slug,
            'status' => Page::STATUS_DRAFT,
            'sections' => [],
        ]);
    }

    private function actingEditor(): void
    {
        Permission::findOrCreate('manage pages');
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('manage pages');
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);
    }
}
