<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Services\Appearance\AboutPageLayout;
use App\Support\FeatureModules;
use Database\Seeders\AboutPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AboutPageSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_layout_includes_timeline_and_arabic_overlays(): void
    {
        $sections = AboutPageLayout::sections();
        $this->assertNotEmpty($sections);

        $json = json_encode($sections);
        $this->assertNotFalse($json);
        $this->assertStringContainsString('"type":"page_header"', $json);
        $this->assertStringContainsString('"type":"timeline"', $json);
        $this->assertStringContainsString('"type":"stats"', $json);
        $this->assertStringContainsString('"type":"feature_grid"', $json);
        $this->assertStringContainsString('"type":"image_text"', $json);
        $this->assertStringContainsString('"type":"cta"', $json);
        $this->assertStringContainsString('2014', $json);
        $this->assertStringContainsString('"translations"', $json);
        $this->assertStringContainsString('"ar"', $json);
        $this->assertStringContainsString('"layout_revision":'.AboutPageLayout::LAYOUT_REVISION, $json);
    }

    public function test_seeder_creates_published_about_page_with_arabic_translation(): void
    {
        $this->assertTrue(FeatureModules::enabled('pages'));

        $this->seed(AboutPageSeeder::class);

        $page = Page::query()->where('slug', 'about')->with('translations')->first();
        $this->assertNotNull($page);
        $this->assertSame(Page::STATUS_PUBLISHED, $page->status);
        $this->assertNotEmpty($page->sections);
        $this->assertTrue($page->translations->contains(fn ($t) => $t->locale === 'ar'));
        $this->assertSame('من نحن', $page->translations->firstWhere('locale', 'ar')?->title);

        $this->getJson('/api/public/pages/about', ['X-Content-Locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('slug', 'about')
            ->assertJsonPath('title', 'About');

        $this->getJson('/api/public/pages/about', ['X-Content-Locale' => 'ar'])
            ->assertOk()
            ->assertJsonPath('slug', 'about')
            ->assertJsonPath('title', 'من نحن');

        $this->seed(AboutPageSeeder::class);
        $this->assertSame(1, Page::query()->where('slug', 'about')->count());
        $this->assertSame(1, $page->fresh()->translations()->where('locale', 'ar')->count());
    }

    public function test_admin_list_includes_seeded_about_after_cache_bump(): void
    {
        Permission::findOrCreate('manage pages');
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('manage pages');
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        $this->seed(AboutPageSeeder::class);

        $en = $this->getJson('/api/v1/pages', ['X-Content-Locale' => 'en']);
        $en->assertOk();
        $slugs = collect($en->json())->pluck('slug')->all();
        $this->assertContains('about', $slugs);

        $ar = $this->getJson('/api/v1/pages', ['X-Content-Locale' => 'ar']);
        $ar->assertOk();
        $arRows = collect($ar->json());
        $this->assertTrue($arRows->contains(fn ($row) => ($row['slug'] ?? null) === 'about'));
        $about = $arRows->first(fn ($row) => ($row['slug'] ?? null) === 'about');
        $this->assertSame('من نحن', $about['title'] ?? null);
    }
}
