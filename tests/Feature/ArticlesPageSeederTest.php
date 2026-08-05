<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Services\Appearance\ArticlesPageLayout;
use App\Support\FeatureModules;
use Database\Seeders\ArticlesPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ArticlesPageSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_articles_page_layout_includes_blog_posts_list_kit(): void
    {
        $sections = ArticlesPageLayout::sections();
        $this->assertNotEmpty($sections);

        $json = json_encode($sections);
        $this->assertNotFalse($json);
        $this->assertStringContainsString('"type":"page_header"', $json);
        $this->assertStringContainsString('"type":"blog_posts_list"', $json);
        $this->assertStringContainsString('"type":"cta"', $json);
        $this->assertStringContainsString('Articles', $json);
        $this->assertStringContainsString('"translations"', $json);
        $this->assertStringContainsString('"ar"', $json);
        $this->assertStringContainsString('"layout_revision":'.ArticlesPageLayout::LAYOUT_REVISION, $json);
    }

    public function test_seeder_creates_published_articles_page_with_arabic_translation(): void
    {
        $this->assertTrue(FeatureModules::enabled('pages'));

        $this->seed(ArticlesPageSeeder::class);

        $page = Page::query()->where('slug', 'articles')->with('translations')->first();
        $this->assertNotNull($page);
        $this->assertSame(Page::STATUS_PUBLISHED, $page->status);
        $this->assertNotEmpty($page->sections);
        $this->assertTrue($page->translations->contains(fn ($t) => $t->locale === 'ar'));
        $this->assertSame('المقالات', $page->translations->firstWhere('locale', 'ar')?->title);

        $this->getJson('/api/public/pages/articles', ['X-Content-Locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('slug', 'articles')
            ->assertJsonPath('title', 'Articles');

        $this->getJson('/api/public/pages/articles', ['X-Content-Locale' => 'ar'])
            ->assertOk()
            ->assertJsonPath('slug', 'articles')
            ->assertJsonPath('title', 'المقالات');

        $this->seed(ArticlesPageSeeder::class);
        $this->assertSame(1, Page::query()->where('slug', 'articles')->count());
    }

    public function test_admin_list_includes_seeded_articles_after_cache_bump(): void
    {
        Permission::findOrCreate('manage pages');
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('manage pages');
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        $this->seed(ArticlesPageSeeder::class);

        $en = $this->getJson('/api/v1/pages', ['X-Content-Locale' => 'en']);
        $en->assertOk();
        $this->assertContains('articles', collect($en->json())->pluck('slug')->all());
    }
}
