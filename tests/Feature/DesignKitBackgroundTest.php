<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PublicMarketingShellService;
use App\Support\DesignKitResolver;
use App\Support\ProjectSettingsStore;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DesignKitBackgroundTest extends TestCase
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

    public function test_admin_saves_page_backgrounds_and_site_meta_emits_scoped_rules(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/appearance/design-kit', [
                'background' => [
                    'light' => ['type' => 'color', 'color' => '#F8FAFC'],
                    'dark' => ['type' => 'gradient', 'color' => '#020617', 'color_end' => '#0f172acc', 'angle' => 180],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.background.light.type', 'color')
            ->assertJsonPath('data.background.light.color', '#f8fafc')
            ->assertJsonPath('data.background.dark.color_end', '#0f172acc')
            ->assertJsonPath('data.background.dark.angle', 180);

        $css = $this->getJson('/api/public/site-meta')->assertOk()->json('data.design_kit_css');
        $this->assertStringContainsString(':root:not(.dark) [data-public-page]{background:#f8fafc}', $css);
        $this->assertStringContainsString(
            '.dark [data-public-page]{background:linear-gradient(180deg,#020617,#0f172acc)}',
            $css,
        );
    }

    public function test_dark_only_background_leaves_light_mode_on_the_theme_gradient(): void
    {
        $css = DesignKitResolver::cssVariables(DesignKitResolver::normalize([
            'background' => ['dark' => ['type' => 'color', 'color' => '#000000']],
        ]));

        $this->assertStringContainsString('.dark [data-public-page]{background:#000000}', $css);
        $this->assertStringNotContainsString(':root:not(.dark) [data-public-page]', $css);
    }

    public function test_theme_type_and_missing_colours_emit_no_background_rules(): void
    {
        $kit = DesignKitResolver::normalize([
            'background' => [
                'light' => ['type' => 'theme', 'color' => '#ffffff'],
                'dark' => ['type' => 'color', 'color' => ''],
            ],
        ]);

        $this->assertSame('theme', $kit['background']['light']['type']);
        $this->assertStringNotContainsString('[data-public-page]', DesignKitResolver::cssVariables($kit));
    }

    public function test_invalid_background_values_are_rejected(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/appearance/design-kit', [
                'background' => [
                    'light' => ['type' => 'image', 'color' => 'red;}body{display:none'],
                    'dark' => ['angle' => 999],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['background.light.type', 'background.light.color', 'background.dark.angle']);
    }

    public function test_editor_cannot_update_page_background(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('editor');

        $this->actingAs($editor, 'sanctum')
            ->putJson('/api/appearance/design-kit', ['background' => ['dark' => ['type' => 'color', 'color' => '#000000']]])
            ->assertForbidden();
    }
}
