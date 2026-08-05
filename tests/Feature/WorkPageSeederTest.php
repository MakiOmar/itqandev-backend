<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Services\Appearance\WorkPageLayout;
use App\Support\FeatureModules;
use Database\Seeders\WorkPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkPageSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_page_layout_includes_projects_list_kit(): void
    {
        $sections = WorkPageLayout::sections();
        $this->assertNotEmpty($sections);

        $json = json_encode($sections);
        $this->assertNotFalse($json);
        $this->assertStringContainsString('"type":"page_header"', $json);
        $this->assertStringContainsString('"type":"projects_list"', $json);
        $this->assertStringContainsString('"type":"cta"', $json);
        $this->assertStringContainsString('"translations"', $json);
        $this->assertStringContainsString('"ar"', $json);
        $this->assertStringContainsString('"layout_revision":'.WorkPageLayout::LAYOUT_REVISION, $json);
    }

    public function test_seeder_creates_published_work_page_with_arabic_translation(): void
    {
        $this->assertTrue(FeatureModules::enabled('pages'));

        $this->seed(WorkPageSeeder::class);

        $page = Page::query()->where('slug', 'work')->with('translations')->first();
        $this->assertNotNull($page);
        $this->assertSame(Page::STATUS_PUBLISHED, $page->status);
        $this->assertNotEmpty($page->sections);
        $this->assertTrue($page->translations->contains(fn ($t) => $t->locale === 'ar'));
        $this->assertSame('أعمالنا', $page->translations->firstWhere('locale', 'ar')?->title);

        $this->getJson('/api/public/pages/work', ['X-Content-Locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('slug', 'work')
            ->assertJsonPath('title', 'Work');

        $this->getJson('/api/public/pages/work', ['X-Content-Locale' => 'ar'])
            ->assertOk()
            ->assertJsonPath('slug', 'work')
            ->assertJsonPath('title', 'أعمالنا');

        $this->seed(WorkPageSeeder::class);
        $this->assertSame(1, Page::query()->where('slug', 'work')->count());
    }

    public function test_admin_list_includes_seeded_work_after_cache_bump(): void
    {
        Permission::findOrCreate('manage pages');
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('manage pages');
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        $this->seed(WorkPageSeeder::class);

        $en = $this->getJson('/api/v1/pages', ['X-Content-Locale' => 'en']);
        $en->assertOk();
        $this->assertContains('work', collect($en->json())->pluck('slug')->all());
    }
}
