<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        \App\Support\ProjectSettingsStore::save(['site_name' => 'Test']);
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    public function test_editor_cannot_get_settings(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('editor');

        $this->withHeaders($this->bearerHeaders($editor))
            ->getJson('/api/settings')
            ->assertForbidden();
    }

    public function test_company_cannot_update_settings(): void
    {
        $company = User::factory()->create();
        $company->assignRole('company');

        $this->withHeaders($this->bearerHeaders($company))
            ->putJson('/api/settings', ['site_name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_admin_can_get_settings(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $this->withHeaders($this->bearerHeaders($admin))
            ->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
