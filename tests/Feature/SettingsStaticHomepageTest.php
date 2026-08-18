<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Support\CmsPublicPaths;
use App\Support\MarketingSettingsCache;
use App\Support\ProjectSettingsStore;
use App\Support\StaticHomepage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsStaticHomepageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        ProjectSettingsStore::save(['site_name' => 'Test']);
        MarketingSettingsCache::forgetAll();
    }

    /**
     * @return array<string, string>
     */
    private function adminHeaders(): array
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        return ['Authorization' => 'Bearer '.$admin->createToken('test')->plainTextToken];
    }

    public function test_public_site_meta_defaults_to_appearance_homepage(): void
    {
        $this->getJson('/api/public/site-meta')
            ->assertOk()
            ->assertJsonPath('data.show_on_front', StaticHomepage::SHOW_BUILDER)
            ->assertJsonPath('data.front_page_slug', null);
    }

    public function test_admin_can_assign_a_published_page_as_front_page(): void
    {
        $home = Page::query()->where('slug', 'home')->first();
        $this->assertNotNull($home);

        $this->withHeaders($this->adminHeaders())
            ->putJson('/api/settings', [
                'show_on_front' => StaticHomepage::SHOW_PAGE,
                'page_on_front' => $home->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.show_on_front', StaticHomepage::SHOW_PAGE)
            ->assertJsonPath('data.page_on_front', $home->id);

        $this->getJson('/api/public/site-meta')
            ->assertOk()
            ->assertJsonPath('data.show_on_front', StaticHomepage::SHOW_PAGE)
            ->assertJsonPath('data.front_page_slug', 'home');

        $this->assertSame('/', CmsPublicPaths::pathForPageSlug('home'));
    }

    public function test_unpublished_front_page_falls_back_to_builder_on_public_meta(): void
    {
        $home = Page::query()->where('slug', 'home')->first();
        $this->assertNotNull($home);
        $home->status = Page::STATUS_DRAFT;
        $home->save();

        ProjectSettingsStore::merge([
            'show_on_front' => StaticHomepage::SHOW_PAGE,
            'page_on_front' => $home->id,
        ]);
        MarketingSettingsCache::forgetAll();

        $this->getJson('/api/public/site-meta')
            ->assertOk()
            ->assertJsonPath('data.show_on_front', StaticHomepage::SHOW_BUILDER)
            ->assertJsonPath('data.front_page_slug', null);
    }
}
