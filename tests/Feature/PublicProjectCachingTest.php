<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectTranslation;
use App\Models\User;
use App\Support\ProjectSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CountsTableQueries;
use Tests\TestCase;

class PublicProjectCachingTest extends TestCase
{
    use CountsTableQueries, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ProjectSettingsStore::save([
            'default_locale' => 'en',
            'site_languages' => [
                ['code' => 'en', 'label' => 'English', 'native_label' => 'English', 'rtl' => false],
                ['code' => 'ar', 'label' => 'Arabic', 'native_label' => 'العربية', 'rtl' => true],
            ],
        ]);
    }

    public function test_empty_translation_rows_do_not_count_as_locale_content(): void
    {
        $translated = $this->project('translated');
        ProjectTranslation::query()->create(['project_id' => $translated->id, 'locale' => 'ar', 'title' => 'مترجم']);
        $empty = $this->project('empty-translation');
        ProjectTranslation::query()->create(['project_id' => $empty->id, 'locale' => 'ar', 'title' => '']);

        $this->withHeader('X-Content-Locale', 'ar')
            ->getJson('/api/public/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'مترجم')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_list_reflects_edits_and_category_renames(): void
    {
        $category = Category::query()->create(['name' => 'Brand', 'slug' => 'brand']);
        $project = $this->project('rebrand');
        $project->categories()->attach($category->id);
        $this->getJson('/api/public/projects')->assertJsonPath('data.0.categories.0.name', 'Brand');

        $project->update(['title' => 'Rebrand v2']);
        $category->update(['name' => 'Branding']);

        $this->getJson('/api/public/projects')
            ->assertJsonPath('data.0.title', 'Rebrand v2')
            ->assertJsonPath('data.0.categories.0.name', 'Branding');
    }

    public function test_paging_and_filters_are_bounded(): void
    {
        $this->getJson('/api/public/projects?page=abc')->assertUnprocessable();
        $this->getJson('/api/public/projects?page=100000')->assertUnprocessable();
        $this->getJson('/api/public/projects?category_slug='.str_repeat('a', 300))->assertUnprocessable();
    }

    public function test_unknown_filter_slug_returns_empty_page_without_querying_projects(): void
    {
        $this->project('visible');
        $this->getJson('/api/public/projects?category_slug=nope')->assertOk();

        $queries = $this->queriesAgainst('projects', fn () => $this->getJson('/api/public/projects?category_slug=another-nope')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0));
        $this->assertSame(0, $queries);
    }

    public function test_show_is_cached_including_not_found(): void
    {
        $this->project('case-study');
        $this->getJson('/api/public/projects/case-study')->assertOk()->assertJsonPath('data.slug', 'case-study');

        $this->assertSame(0, $this->queriesAgainst('projects', fn () => $this->getJson('/api/public/projects/Case-Study')
            ->assertOk()
            ->assertJsonPath('data.slug', 'case-study')));

        $this->getJson('/api/public/projects/missing')->assertNotFound();
        $this->assertSame(0, $this->queriesAgainst('projects', fn () => $this->getJson('/api/public/projects/missing')
            ->assertNotFound()));
    }

    public function test_draft_preview_is_never_served_from_the_public_cache(): void
    {
        $this->project('secret', 'draft');
        $this->getJson('/api/public/projects/secret')->assertNotFound();

        Role::findOrCreate('editor');
        $editor = User::factory()->create();
        $editor->assignRole('editor');
        Sanctum::actingAs($editor);
        $this->getJson('/api/public/projects/secret')->assertOk()->assertJsonPath('data.status', 'draft');
    }

    public function test_translation_removed_through_admin_drops_project_from_locale_list(): void
    {
        $project = $this->project('bilingual');
        ProjectTranslation::query()->create(['project_id' => $project->id, 'locale' => 'ar', 'title' => 'ثنائي']);
        $this->withHeader('X-Content-Locale', 'ar')->getJson('/api/public/projects')->assertJsonCount(1, 'data');

        Role::findOrCreate('editor');
        $editor = User::factory()->create();
        $editor->assignRole('editor');
        Sanctum::actingAs($editor);
        $this->putJson('/api/v1/projects/'.$project->id, [
            'translations' => [['locale' => 'ar', 'title' => '', 'summary' => '', 'description' => '']],
        ])->assertOk();
        $this->assertSame(0, $project->translations()->count());

        $this->withHeader('X-Content-Locale', 'ar')->getJson('/api/public/projects')->assertJsonCount(0, 'data');
    }

    public function test_seo_edit_shows_on_cached_project(): void
    {
        $project = $this->project('seo-target');
        $this->getJson('/api/public/projects/seo-target')->assertOk();

        Role::findOrCreate('editor');
        $editor = User::factory()->create();
        $editor->assignRole('editor');
        Sanctum::actingAs($editor);
        $this->putJson('/api/v1/seo/project/'.$project->id, ['locale' => 'en', 'meta_title' => 'Fresh'])->assertOk();

        $this->getJson('/api/public/projects/seo-target')->assertJsonPath('data.seo_meta.meta_title', 'Fresh');
    }

    private function project(string $slug, string $status = 'published'): Project
    {
        return Project::query()->create([
            'title' => ucfirst($slug),
            'slug' => $slug,
            'summary' => 'Summary',
            'status' => $status,
            'featured' => false,
            'published_at' => now(),
        ]);
    }
}
