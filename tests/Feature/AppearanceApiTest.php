<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Appearance\HomepageBuilderService;
use App\Services\PublicMarketingShellService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AppearanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        \App\Support\ProjectSettingsStore::save(['site_name' => 'Test']);
        Cache::forget('project-settings');
        PublicMarketingShellService::forgetShellCaches();
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    private function admin(): User
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        return $admin;
    }

    public function test_unauthenticated_cannot_get_homepage_appearance(): void
    {
        $this->getJson('/api/appearance/homepage')->assertUnauthorized();
    }

    public function test_editor_forbidden_from_appearance(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('editor');

        $this->withHeaders($this->bearerHeaders($editor))
            ->getJson('/api/appearance/homepage')
            ->assertForbidden();
    }

    public function test_admin_can_get_and_put_homepage(): void
    {
        $admin = $this->admin();

        $this->withHeaders($this->bearerHeaders($admin))
            ->getJson('/api/appearance/homepage')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(7, 'data.sections');

        $payload = [
            'sections' => [
                [
                    'id' => 'sec_hero',
                    'type' => 'hero',
                    'enabled' => true,
                    'layout_width' => 'full',
                    'settings' => ['headline' => 'Hello builder'],
                ],
                [
                    'type' => 'cta',
                    'enabled' => true,
                    'settings' => [],
                ],
            ],
        ];

        $this->withHeaders($this->bearerHeaders($admin))
            ->putJson('/api/appearance/homepage', $payload)
            ->assertOk()
            ->assertJsonPath('data.sections.0.settings.headline', 'Hello builder');
    }

    public function test_admin_can_get_and_put_footer(): void
    {
        $admin = $this->admin();

        $get = $this->withHeaders($this->bearerHeaders($admin))
            ->getJson('/api/appearance/footer')
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('sections', $get);
        $this->assertNotEmpty($get['sections']);

        $this->withHeaders($this->bearerHeaders($admin))
            ->putJson('/api/appearance/footer', ['sections' => $get['sections']])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_get_and_put_header(): void
    {
        $admin = $this->admin();

        $get = $this->withHeaders($this->bearerHeaders($admin))
            ->getJson('/api/appearance/header')
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('sections', $get);
        $this->assertNotEmpty($get['sections']);

        $this->withHeaders($this->bearerHeaders($admin))
            ->putJson('/api/appearance/header', ['sections' => $get['sections']])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_registries_endpoint(): void
    {
        $admin = $this->admin();

        $this->withHeaders($this->bearerHeaders($admin))
            ->getJson('/api/appearance/registries')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'homepage_sections' => [
                        ['type', 'label', 'max_instances', 'default_settings', 'settings_fields'],
                    ],
                    'kits' => [
                        ['type', 'label', 'category', 'max_instances', 'default_settings', 'settings_fields'],
                    ],
                ],
            ])
            ->assertJsonMissingPath('data.footer_blocks');
    }

    public function test_public_shell_includes_homepage_sections_header_and_footer(): void
    {
        app(HomepageBuilderService::class)->save([
            'sections' => [
                ['type' => 'hero', 'enabled' => true],
                ['type' => 'cta', 'enabled' => true],
            ],
        ]);

        $this->getJson('/api/public/shell')
            ->assertOk()
            ->assertJsonPath('data.homepage_sections.0.type', 'hero')
            ->assertJsonStructure([
                'data' => [
                    'header' => ['sections'],
                    'footer' => ['sections'],
                ],
            ]);
    }
}
