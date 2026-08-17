<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PublicMarketingShellService;
use App\Support\ProjectSettingsStore;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SiteSettingsLocalizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedSettingsFile(array $overrides = []): void
    {
        $base = [
            'default_locale' => 'en',
            'site_name' => 'CredoCode EN',
            'site_description' => 'English description',
            'site_address' => '123 Main St',
            'site_languages' => [
                ['code' => 'en', 'label' => 'English', 'native_label' => 'English', 'rtl' => false],
                ['code' => 'ar', 'label' => 'Arabic', 'native_label' => 'العربية', 'rtl' => true],
            ],
            'settings_translations' => [
                'ar' => [
                    'site_name' => 'كريدوكود',
                    'site_description' => 'وصف عربي',
                ],
            ],
            'marketing_site_content' => [
                'faq' => [
                    ['question' => 'English question?', 'answer' => 'English answer'],
                ],
            ],
        ];

        $payload = array_merge($base, $overrides);
        if (array_key_exists('settings_translations', $overrides)) {
            $payload['settings_translations'] = $overrides['settings_translations'];
        }
        if (array_key_exists('marketing_site_content', $overrides)) {
            $payload['marketing_site_content'] = $overrides['marketing_site_content'];
        }

        ProjectSettingsStore::save($payload);
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

    public function test_public_meta_returns_localized_site_name_for_arabic(): void
    {
        $this->seedSettingsFile();

        $this->getJson('/api/public/site-meta?locale=ar')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.site_name', 'كريدوكود')
            ->assertJsonPath('data.site_description', 'وصف عربي');
    }

    public function test_public_meta_falls_back_to_primary_when_translation_missing(): void
    {
        $this->seedSettingsFile([
            'settings_translations' => [
                'ar' => ['site_name' => 'كريدوكود'],
            ],
        ]);

        $this->getJson('/api/public/site-meta?locale=ar')
            ->assertJsonPath('data.site_name', 'كريدوكود')
            ->assertJsonPath('data.site_description', 'English description');
    }

    public function test_public_meta_default_locale_returns_primary_fields(): void
    {
        $this->seedSettingsFile();

        $this->getJson('/api/public/site-meta?locale=en')
            ->assertJsonPath('data.site_name', 'CredoCode EN')
            ->assertJsonPath('data.site_description', 'English description');
    }

    public function test_shell_site_meta_differs_per_cached_locale(): void
    {
        $this->seedSettingsFile();

        $en = $this->getJson('/api/public/shell?locale=en')->json('data.site_meta.site_name');
        $ar = $this->getJson('/api/public/shell?locale=ar')->json('data.site_meta.site_name');

        $this->assertSame('CredoCode EN', $en);
        $this->assertSame('كريدوكود', $ar);
    }

    public function test_public_site_content_is_not_exposed_with_raw_settings_translations(): void
    {
        $this->seedSettingsFile();

        $response = $this->getJson('/api/public/site-meta?locale=ar');
        $response->assertOk();
        $this->assertArrayNotHasKey('settings_translations', $response->json('data') ?? []);
    }

    public function test_put_settings_persists_settings_translations(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seedSettingsFile(['settings_translations' => []]);

        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $this->withHeaders($this->bearerHeaders($admin))
            ->putJson('/api/settings', [
                'settings_translations' => [
                    'ar' => ['site_name' => 'اسم محدث'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $stored = ProjectSettingsStore::load();
        $this->assertSame('اسم محدث', $stored['settings_translations']['ar']['site_name'] ?? null);
    }

    public function test_put_settings_merges_partial_settings_translations_without_wiping_other_locales(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seedSettingsFile();

        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $this->withHeaders($this->bearerHeaders($admin))
            ->putJson('/api/settings', [
                'settings_translations' => [
                    'ar' => ['site_address' => 'عنوان جديد'],
                ],
            ])
            ->assertOk();

        $stored = ProjectSettingsStore::load();
        $this->assertSame('كريدوكود', $stored['settings_translations']['ar']['site_name'] ?? null);
        $this->assertSame('عنوان جديد', $stored['settings_translations']['ar']['site_address'] ?? null);
    }
}
