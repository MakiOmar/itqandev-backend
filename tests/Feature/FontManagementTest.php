<?php

namespace Tests\Feature;

use App\Models\Font;
use App\Models\User;
use App\Support\TypographyResolver;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FontManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    public function test_public_site_meta_includes_typography(): void
    {
        $this->getJson('/api/public/site-meta')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'typography' => [
                        'mode',
                        'ltr' => ['css_family', 'fallback_stack', 'sources'],
                        'rtl' => ['css_family', 'fallback_stack', 'sources'],
                    ],
                ],
            ])
            ->assertJsonPath('data.typography.mode', TypographyResolver::MODE_SYSTEM);
    }

    public function test_font_crud_requires_manage_fonts_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->withHeaders($this->bearerHeaders($viewer))
            ->getJson('/api/v1/fonts')
            ->assertForbidden();
    }

    public function test_admin_can_create_and_list_fonts(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $this->withHeaders($this->bearerHeaders($admin))
            ->postJson('/api/v1/fonts', [
                'name' => 'Brand Sans',
                'css_family' => 'Brand Sans',
                'file_woff2' => '/storage/fonts/brand.woff2',
            ])
            ->assertCreated()
            ->assertJsonPath('data.css_family', 'Brand Sans');

        $this->withHeaders($this->bearerHeaders($admin))
            ->getJson('/api/v1/fonts')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Brand Sans']);
    }

    public function test_font_upload_returns_relative_storage_path(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $file = UploadedFile::fake()->create(
            'koftarabic-extralight-webfont.ttf',
            32,
            'application/octet-stream'
        );

        $response = $this->withHeaders($this->bearerHeaders($admin))
            ->post('/api/v1/fonts/upload', [
                'file' => $file,
                'format' => 'ttf',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.format', 'ttf');

        $url = $response->json('data.url');
        $this->assertIsString($url);
        $this->assertStringStartsWith('/storage/fonts/', $url);
        Storage::disk('public')->assertExists('fonts/'.basename($url));
    }

    public function test_font_store_normalizes_http_storage_urls(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $this->withHeaders($this->bearerHeaders($admin))
            ->postJson('/api/v1/fonts', [
                'name' => 'HTTP Font',
                'css_family' => 'HTTP Font',
                'file_ttf' => 'http://127.0.0.1/storage/fonts/sample.ttf',
            ])
            ->assertCreated()
            ->assertJsonPath('data.file_ttf', '/storage/fonts/sample.ttf');
    }

    public function test_custom_typography_allows_partial_fonts_and_falls_back_to_defaults(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $ltr = Font::query()->create([
            'name' => 'Partial LTR',
            'css_family' => 'Partial LTR',
            'file_woff2' => '/storage/fonts/partial-ltr.woff2',
        ]);

        $this->withHeaders($this->bearerHeaders($admin))
            ->putJson('/api/settings', [
                'font_mode' => TypographyResolver::MODE_CUSTOM,
                'font_ltr_id' => $ltr->id,
            ])
            ->assertOk();

        $this->getJson('/api/public/site-meta')
            ->assertOk()
            ->assertJsonPath('data.typography.mode', TypographyResolver::MODE_CUSTOM)
            ->assertJsonPath('data.typography.ltr.css_family', 'Partial LTR')
            ->assertJsonPath('data.typography.rtl.css_family', 'Cairo')
            ->assertJsonPath('data.typography.rtl.google_css_href', 'https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap');
    }

    public function test_font_in_use_cannot_be_deleted(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $ltr = Font::query()->create([
            'name' => 'LTR Font',
            'css_family' => 'LTR Font',
            'file_woff2' => '/storage/fonts/ltr.woff2',
        ]);
        $rtl = Font::query()->create([
            'name' => 'RTL Font',
            'css_family' => 'RTL Font',
            'file_woff2' => '/storage/fonts/rtl.woff2',
        ]);

        $this->withHeaders($this->bearerHeaders($admin))
            ->putJson('/api/settings', [
                'font_mode' => TypographyResolver::MODE_CUSTOM,
                'font_ltr_id' => $ltr->id,
                'font_rtl_id' => $rtl->id,
            ])
            ->assertOk();

        $this->withHeaders($this->bearerHeaders($admin))
            ->deleteJson("/api/v1/fonts/{$ltr->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'This font is assigned in Typography settings and cannot be deleted.');
    }

    public function test_typography_settings_partial_put_persists_font_mode(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $rtl = Font::query()->create([
            'name' => 'Persist RTL',
            'css_family' => 'Persist RTL',
            'file_woff2' => '/storage/fonts/persist-rtl.woff2',
        ]);

        $this->withHeaders($this->bearerHeaders($admin))
            ->putJson('/api/settings', [
                'font_mode' => TypographyResolver::MODE_CUSTOM,
                'font_rtl_id' => $rtl->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.font_mode', TypographyResolver::MODE_CUSTOM)
            ->assertJsonPath('data.font_rtl_id', $rtl->id);

        $this->withHeaders($this->bearerHeaders($admin))
            ->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.font_mode', TypographyResolver::MODE_CUSTOM)
            ->assertJsonPath('data.font_rtl_id', $rtl->id);
    }

    public function test_custom_typography_resolves_in_public_site_meta(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $ltr = Font::query()->create([
            'name' => 'Custom LTR',
            'css_family' => 'Custom LTR',
            'file_woff2' => '/storage/fonts/custom-ltr.woff2',
        ]);
        $rtl = Font::query()->create([
            'name' => 'Custom RTL',
            'css_family' => 'Custom RTL',
            'file_woff2' => '/storage/fonts/custom-rtl.woff2',
        ]);

        $this->withHeaders($this->bearerHeaders($admin))
            ->putJson('/api/settings', [
                'font_mode' => TypographyResolver::MODE_CUSTOM,
                'font_ltr_id' => $ltr->id,
                'font_rtl_id' => $rtl->id,
            ])
            ->assertOk();

        $this->getJson('/api/public/site-meta')
            ->assertOk()
            ->assertJsonPath('data.typography.mode', TypographyResolver::MODE_CUSTOM)
            ->assertJsonPath('data.typography.ltr.css_family', 'Custom LTR')
            ->assertJsonPath('data.typography.ltr.fallback_stack', "'Custom LTR', Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif")
            ->assertJsonPath('data.typography.rtl.css_family', 'Custom RTL')
            ->assertJsonPath('data.typography.ltr.sources.woff2', '/storage/fonts/custom-ltr.woff2');
    }
}
