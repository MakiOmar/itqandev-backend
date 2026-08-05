<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Services\Appearance\PortfolioPageLayout;
use App\Support\FeatureModules;
use Database\Seeders\PortfolioPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortfolioPageSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_portfolio_page_layout_includes_projects_list_kit(): void
    {
        $sections = PortfolioPageLayout::sections();
        $this->assertNotEmpty($sections);

        $json = json_encode($sections);
        $this->assertNotFalse($json);
        $this->assertStringContainsString('"type":"page_header"', $json);
        $this->assertStringContainsString('"type":"projects_list"', $json);
        $this->assertStringContainsString('"type":"cta"', $json);
        $this->assertStringContainsString('Portfolio', $json);
        $this->assertStringContainsString('"translations"', $json);
        $this->assertStringContainsString('"ar"', $json);
        $this->assertStringContainsString('"layout_revision":'.PortfolioPageLayout::LAYOUT_REVISION, $json);
    }

    public function test_seeder_creates_published_portfolio_page_with_arabic_translation(): void
    {
        $this->assertTrue(FeatureModules::enabled('pages'));

        $this->seed(PortfolioPageSeeder::class);

        $page = Page::query()->where('slug', 'portfolio')->with('translations')->first();
        $this->assertNotNull($page);
        $this->assertSame(Page::STATUS_PUBLISHED, $page->status);
        $this->assertNotEmpty($page->sections);
        $this->assertTrue($page->translations->contains(fn ($t) => $t->locale === 'ar'));
        $this->assertSame('المحفظة', $page->translations->firstWhere('locale', 'ar')?->title);
        $this->assertSame(0, Page::query()->where('slug', 'work')->count());

        $this->getJson('/api/public/pages/portfolio', ['X-Content-Locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('slug', 'portfolio')
            ->assertJsonPath('title', 'Portfolio');

        $this->getJson('/api/public/pages/portfolio', ['X-Content-Locale' => 'ar'])
            ->assertOk()
            ->assertJsonPath('slug', 'portfolio')
            ->assertJsonPath('title', 'المحفظة');

        $this->seed(PortfolioPageSeeder::class);
        $this->assertSame(1, Page::query()->where('slug', 'portfolio')->count());
    }

    public function test_seeder_renames_legacy_work_slug_to_portfolio(): void
    {
        $legacy = Page::create([
            'title' => 'Work',
            'slug' => 'work',
            'excerpt' => 'Old',
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now(),
            'content_locale' => null,
            'sections' => [],
        ]);

        $this->seed(PortfolioPageSeeder::class);

        $this->assertSame(0, Page::query()->where('slug', 'work')->count());
        $page = Page::query()->where('slug', 'portfolio')->first();
        $this->assertNotNull($page);
        $this->assertSame($legacy->id, $page->id);
        $this->assertSame('Portfolio', $page->title);
    }

    public function test_admin_list_includes_seeded_portfolio_after_cache_bump(): void
    {
        Permission::findOrCreate('manage pages');
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('manage pages');
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        $this->seed(PortfolioPageSeeder::class);

        $en = $this->getJson('/api/v1/pages', ['X-Content-Locale' => 'en']);
        $en->assertOk();
        $this->assertContains('portfolio', collect($en->json())->pluck('slug')->all());
    }
}
