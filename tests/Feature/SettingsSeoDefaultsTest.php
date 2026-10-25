<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PublicMarketingShellService;
use App\Support\ProjectSettingsStore;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SettingsSeoDefaultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->saveSettings([]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function saveSettings(array $overrides): void
    {
        ProjectSettingsStore::save(array_merge([
            'default_locale' => 'en',
            'site_name' => 'Credo',
            'site_languages' => [
                ['code' => 'en', 'label' => 'English', 'native_label' => 'English', 'rtl' => false],
                ['code' => 'ar', 'label' => 'Arabic', 'native_label' => 'العربية', 'rtl' => true],
            ],
        ], $overrides));
        Cache::forget('project-settings');
        PublicMarketingShellService::forgetShellCaches();
    }

    /**
     * @return array<string, string>
     */
    private function adminHeaders(): array
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->firstOrFail();

        return ['Authorization' => 'Bearer '.$admin->createToken('test')->plainTextToken];
    }

    public function test_site_meta_exposes_empty_defaults_shape(): void
    {
        $this->getJson('/api/public/site-meta?locale=en')
            ->assertOk()
            ->assertJsonPath('data.seo_defaults.title_template', '')
            ->assertJsonPath('data.seo_defaults.og_image', null)
            ->assertJsonPath('data.seo_defaults.facebook_app_id', '')
            ->assertJsonPath('data.seo_defaults.types.page.meta_description', '')
            ->assertJsonPath('data.seo_defaults.types.blog_post.twitter_card', '');
    }

    public function test_admin_saves_sanitized_defaults(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->putJson('/api/settings', [
                'seo_defaults' => [
                    'title_template' => '{title} | {site}',
                    'meta_description' => "<b>Hello</b>\n world",
                    'og_image' => '/storage/media/card.png',
                    'twitter_card' => 'summary',
                    'types' => [
                        'blog_post' => ['meta_description' => 'Blog default', 'twitter_card' => 'summary_large_image'],
                    ],
                ],
            ])
            ->assertOk();

        $stored = ProjectSettingsStore::load()['seo_defaults'];
        $this->assertSame('Hello world', $stored['meta_description']);
        $this->assertSame('/storage/media/card.png', $stored['og_image']);
        $this->assertSame('Blog default', $stored['types']['blog_post']['meta_description']);
        $this->assertSame('', $stored['types']['page']['title_template']);

        $meta = $this->getJson('/api/public/site-meta?locale=en')->assertOk();
        $meta->assertJsonPath('data.seo_defaults.title_template', '{title} | {site}')
            ->assertJsonPath('data.seo_defaults.types.blog_post.twitter_card', 'summary_large_image');
        $this->assertStringStartsWith('http', (string) $meta->json('data.seo_defaults.og_image'));
        $this->assertStringEndsWith('/storage/media/card.png', (string) $meta->json('data.seo_defaults.og_image'));
    }

    public function test_unsafe_image_and_unknown_card_are_rejected(): void
    {
        $headers = $this->adminHeaders();

        $this->withHeaders($headers)
            ->putJson('/api/settings', ['seo_defaults' => ['og_image' => 'javascript:alert(1)']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['seo_defaults.og_image']);

        $this->withHeaders($headers)
            ->putJson('/api/settings', ['seo_defaults' => ['types' => ['page' => ['og_image' => '//evil.test/x.png']]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['seo_defaults.types.page.og_image']);

        $this->withHeaders($headers)
            ->putJson('/api/settings', ['seo_defaults' => ['twitter_card' => 'player']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['seo_defaults.twitter_card']);

        $this->withHeaders($headers)
            ->putJson('/api/settings', ['seo_defaults' => ['facebook_app_id' => '12345"><script>']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['seo_defaults.facebook_app_id']);
    }

    public function test_facebook_app_id_is_saved_and_shared_by_every_locale(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->putJson('/api/settings', ['seo_defaults' => ['facebook_app_id' => '966242223397117']])
            ->assertOk();

        $this->assertSame('966242223397117', ProjectSettingsStore::load()['seo_defaults']['facebook_app_id']);
        foreach (['en', 'ar'] as $locale) {
            $this->getJson("/api/public/site-meta?locale={$locale}")
                ->assertOk()
                ->assertJsonPath('data.seo_defaults.facebook_app_id', '966242223397117');
        }
    }

    public function test_localized_defaults_do_not_leak_across_locales(): void
    {
        $this->saveSettings([
            'seo_defaults' => [
                'title_template' => '{title} | {site}',
                'meta_description' => 'English default',
                'types' => [
                    'page' => ['meta_description' => 'English page default'],
                    'blog_post' => ['meta_description' => 'English blog default'],
                ],
            ],
            'settings_translations' => [
                'ar' => [
                    'seo_defaults' => [
                        'meta_description' => 'وصف افتراضي',
                        'types' => ['page' => ['meta_description' => 'وصف الصفحات']],
                    ],
                ],
            ],
        ]);

        $this->getJson('/api/public/site-meta?locale=ar')
            ->assertOk()
            ->assertJsonPath('data.seo_defaults.meta_description', 'وصف افتراضي')
            ->assertJsonPath('data.seo_defaults.types.page.meta_description', 'وصف الصفحات')
            ->assertJsonPath('data.seo_defaults.types.blog_post.meta_description', '')
            ->assertJsonPath('data.seo_defaults.title_template', '{title} | {site}');

        $this->getJson('/api/public/site-meta?locale=en')
            ->assertOk()
            ->assertJsonPath('data.seo_defaults.meta_description', 'English default')
            ->assertJsonPath('data.seo_defaults.types.blog_post.meta_description', 'English blog default');
    }

    public function test_admin_saves_localized_defaults_through_settings_translations(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->putJson('/api/settings', [
                'settings_translations' => [
                    'ar' => ['seo_defaults' => ['meta_description' => '<i>وصف</i>', 'og_image' => '/ignored.png']],
                ],
            ])
            ->assertOk();

        $row = ProjectSettingsStore::load()['settings_translations']['ar']['seo_defaults'] ?? [];
        $this->assertSame('وصف', $row['meta_description'] ?? null);
        $this->assertArrayNotHasKey('og_image', $row);
    }

    public function test_guests_and_non_admins_cannot_update_defaults(): void
    {
        $payload = ['seo_defaults' => ['meta_description' => 'Nope']];

        $this->putJson('/api/settings', $payload)->assertUnauthorized();

        $editor = User::factory()->create();
        $editor->assignRole('editor');
        $this->withHeaders(['Authorization' => 'Bearer '.$editor->createToken('test')->plainTextToken])
            ->putJson('/api/settings', $payload)
            ->assertForbidden();

        $this->assertSame('', ProjectSettingsStore::load()['seo_defaults']['meta_description'] ?? '');
    }
}
