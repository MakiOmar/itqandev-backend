<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MarketingSettingsCache;
use App\Support\ProjectSettingsStore;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsDefaultThemeTest extends TestCase
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

    public function test_public_site_meta_defaults_to_system_theme(): void
    {
        $this->getJson('/api/public/site-meta')
            ->assertOk()
            ->assertJsonPath('data.default_theme', 'system');
    }

    public function test_admin_can_set_dark_default_theme(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->putJson('/api/settings', ['default_theme' => 'dark'])
            ->assertOk()
            ->assertJsonPath('data.default_theme', 'dark');

        $this->getJson('/api/public/site-meta')
            ->assertOk()
            ->assertJsonPath('data.default_theme', 'dark');
    }

    public function test_other_saves_keep_the_default_theme(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->putJson('/api/settings', ['default_theme' => 'light'])
            ->assertOk();

        $this->withHeaders($this->adminHeaders())
            ->putJson('/api/settings', ['site_name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.default_theme', 'light');
    }

    public function test_unknown_default_theme_is_rejected(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->putJson('/api/settings', ['default_theme' => 'sepia'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('default_theme');
    }

    public function test_guest_cannot_change_default_theme(): void
    {
        $this->putJson('/api/settings', ['default_theme' => 'dark'])->assertStatus(401);
    }
}
