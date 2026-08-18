<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MarketingSettingsCache;
use App\Support\ProjectSettingsStore;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsSearchEngineIndexingTest extends TestCase
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

    public function test_public_site_meta_defaults_search_engine_indexing_to_true(): void
    {
        $this->getJson('/api/public/site-meta')
            ->assertOk()
            ->assertJsonPath('data.search_engine_indexing', true);
    }

    public function test_admin_can_disable_search_engine_indexing(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->putJson('/api/settings', ['search_engine_indexing' => false])
            ->assertOk()
            ->assertJsonPath('data.search_engine_indexing', false);

        $this->getJson('/api/public/site-meta')
            ->assertOk()
            ->assertJsonPath('data.search_engine_indexing', false);
    }

    public function test_admin_can_re_enable_search_engine_indexing(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->putJson('/api/settings', ['search_engine_indexing' => false])
            ->assertOk();

        $this->withHeaders($this->adminHeaders())
            ->putJson('/api/settings', ['search_engine_indexing' => true])
            ->assertOk()
            ->assertJsonPath('data.search_engine_indexing', true);

        $this->getJson('/api/public/site-meta')
            ->assertOk()
            ->assertJsonPath('data.search_engine_indexing', true);
    }
}
