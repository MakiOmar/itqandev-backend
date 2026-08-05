<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Services\Appearance\ServicesPageLayout;
use App\Support\FeatureModules;
use Database\Seeders\ServicesPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServicesPageSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_page_layout_includes_teaser_and_arabic_overlays(): void
    {
        $sections = ServicesPageLayout::sections();
        $this->assertNotEmpty($sections);

        $json = json_encode($sections);
        $this->assertNotFalse($json);
        $this->assertStringContainsString('"type":"page_header"', $json);
        $this->assertStringContainsString('"type":"services_teaser"', $json);
        $this->assertStringContainsString('"limit":24', $json);
        $this->assertStringContainsString('Our services', $json);
        $this->assertStringContainsString('"translations"', $json);
        $this->assertStringContainsString('"ar"', $json);
        $this->assertStringContainsString('"layout_revision":'.ServicesPageLayout::LAYOUT_REVISION, $json);
    }

    public function test_seeder_creates_published_services_page_with_arabic_translation(): void
    {
        $this->assertTrue(FeatureModules::enabled('pages'));

        $this->seed(ServicesPageSeeder::class);

        $page = Page::query()->where('slug', 'services')->with('translations')->first();
        $this->assertNotNull($page);
        $this->assertSame(Page::STATUS_PUBLISHED, $page->status);
        $this->assertNotEmpty($page->sections);
        $this->assertTrue($page->translations->contains(fn ($t) => $t->locale === 'ar'));
        $this->assertSame('الخدمات', $page->translations->firstWhere('locale', 'ar')?->title);

        $this->getJson('/api/public/pages/services', ['X-Content-Locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('slug', 'services')
            ->assertJsonPath('title', 'Services');

        $this->getJson('/api/public/pages/services', ['X-Content-Locale' => 'ar'])
            ->assertOk()
            ->assertJsonPath('slug', 'services')
            ->assertJsonPath('title', 'الخدمات');

        $this->seed(ServicesPageSeeder::class);
        $this->assertSame(1, Page::query()->where('slug', 'services')->count());
        $this->assertSame(1, $page->fresh()->translations()->where('locale', 'ar')->count());
    }

    public function test_admin_list_includes_seeded_services_after_cache_bump(): void
    {
        Permission::findOrCreate('manage pages');
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('manage pages');
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        $this->seed(ServicesPageSeeder::class);

        $en = $this->getJson('/api/v1/pages', ['X-Content-Locale' => 'en']);
        $en->assertOk();
        $slugs = collect($en->json())->pluck('slug')->all();
        $this->assertContains('services', $slugs);

        $ar = $this->getJson('/api/v1/pages', ['X-Content-Locale' => 'ar']);
        $ar->assertOk();
        $arRows = collect($ar->json());
        $this->assertTrue($arRows->contains(fn ($row) => ($row['slug'] ?? null) === 'services'));
        $services = $arRows->first(fn ($row) => ($row['slug'] ?? null) === 'services');
        $this->assertSame('الخدمات', $services['title'] ?? null);
    }
}
