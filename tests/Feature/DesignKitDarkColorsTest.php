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

class DesignKitDarkColorsTest extends TestCase
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

    public function test_admin_saves_dark_colours_and_unknown_ids_are_dropped(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/appearance/design-kit', [
                'colors' => [
                    'primary' => '#0389a1',
                    'custom' => [['id' => 'brand_blue', 'name' => 'Brand blue', 'value' => '#1d4ed8']],
                ],
                'colors_dark' => [
                    'text' => '#F1F5F9',
                    'brand_blue' => '#60a5fa',
                    'nope' => '#ffffff',
                    'muted' => null,
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.colors_dark.text', '#f1f5f9')
            ->assertJsonPath('data.colors_dark.brand_blue', '#60a5fa')
            ->assertJsonMissingPath('data.colors_dark.nope')
            ->assertJsonMissingPath('data.colors_dark.muted');

        $css = $this->getJson('/api/public/site-meta')->assertOk()->json('data.design_kit_css');
        $this->assertStringStartsWith(':root,.light{', $css);
        $this->assertStringContainsString('.dark{--kit-color-text: #f1f5f9;--kit-color-brand_blue: #60a5fa;}', $css);
    }

    public function test_invalid_dark_colour_is_rejected(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/appearance/design-kit', [
                'colors_dark' => ['primary' => 'red;}body{display:none'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['colors_dark.primary']);
    }

    public function test_editor_cannot_update_design_kit(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('editor');

        $this->actingAs($editor, 'sanctum')
            ->putJson('/api/appearance/design-kit', ['colors_dark' => ['text' => '#ffffff']])
            ->assertForbidden();
    }

    public function test_css_has_no_dark_block_without_dark_colours(): void
    {
        $css = DesignKitResolver::cssVariables(DesignKitResolver::normalize([]));

        $this->assertStringStartsWith(':root,.light{', $css);
        $this->assertStringNotContainsString('.dark{', $css);
    }
}
