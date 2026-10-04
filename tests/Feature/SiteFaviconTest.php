<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PublicMarketingShellService;
use App\Support\ProjectSettingsStore;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SiteFaviconTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        ProjectSettingsStore::save(['site_name' => 'Test']);
        Cache::forget('project-settings');
        PublicMarketingShellService::forgetShellCaches();
    }

    private function admin(): User
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        return $admin;
    }

    public function test_saved_favicon_is_exposed_in_public_site_meta(): void
    {
        $url = 'https://cdn.example.test/storage/media/favicon.png';

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/settings', ['favicon' => $url])
            ->assertOk();

        $this->getJson('/api/public/site-meta')
            ->assertOk()
            ->assertJsonPath('data.favicon', $url);
    }

    public function test_site_meta_favicon_is_null_when_unset(): void
    {
        $this->getJson('/api/public/site-meta')
            ->assertOk()
            ->assertJsonPath('data.favicon', null);
    }

    public function test_favicon_must_be_a_url(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/settings', ['favicon' => 'javascript:alert(1)'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['favicon']);
    }
}
