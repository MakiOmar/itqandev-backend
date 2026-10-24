<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Support\ProjectSettingsStore;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SeoMetaMultilingualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        ProjectSettingsStore::save([
            'default_locale' => 'en',
            'site_languages' => [
                ['code' => 'en', 'label' => 'English', 'native_label' => 'English', 'rtl' => false],
                ['code' => 'ar', 'label' => 'Arabic', 'native_label' => 'العربية', 'rtl' => true],
            ],
        ]);
        Cache::forget('project-settings');
    }

    /**
     * @return array<string, string>
     */
    private function adminHeaders(): array
    {
        return $this->bearerHeaders(User::query()->where('email', 'admin@credocode.test')->firstOrFail());
    }

    private function createBilingualPage(): Page
    {
        $page = Page::create([
            'title' => 'Seo Page EN',
            'slug' => 'seo-page',
            'excerpt' => 'EN excerpt',
            'status' => Page::STATUS_PUBLISHED,
            'content_locale' => 'en',
            'published_at' => now(),
            'sections' => [],
        ]);
        $page->translations()->create(['locale' => 'ar', 'title' => 'صفحة', 'excerpt' => 'مقتطف']);

        return $page;
    }

    public function test_page_seo_is_served_per_locale_without_other_language_rows(): void
    {
        $page = $this->createBilingualPage();
        $headers = $this->adminHeaders();

        // Warm the public cache first so the save must invalidate it.
        $this->getJson('/api/public/pages/seo-page', ['X-Content-Locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('seo_meta', null);

        $this->withHeaders($headers)
            ->putJson("/api/v1/seo/page/{$page->id}", [
                'locale' => 'EN',
                'meta_title' => 'Page meta EN',
                'meta_description' => 'Page description EN',
                'og_image' => 'https://example.test/page.png',
            ])
            ->assertOk()
            ->assertJsonPath('locale', 'en');

        $this->getJson('/api/public/pages/seo-page', ['X-Content-Locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('seo_meta.meta_title', 'Page meta EN')
            ->assertJsonPath('seo_meta.og_image', 'https://example.test/page.png');

        $this->getJson('/api/public/pages/seo-page', ['X-Content-Locale' => 'ar'])
            ->assertOk()
            ->assertJsonPath('title', 'صفحة')
            ->assertJsonPath('seo_meta', null);

        $this->withHeaders($headers)
            ->putJson("/api/v1/seo/page/{$page->id}", ['locale' => 'ar', 'meta_title' => 'عنوان الصفحة'])
            ->assertOk();

        $this->getJson('/api/public/pages/seo-page', ['X-Content-Locale' => 'ar'])
            ->assertOk()
            ->assertJsonPath('seo_meta.meta_title', 'عنوان الصفحة')
            ->assertJsonPath('seo_meta.locale', 'ar');
    }

    public function test_seo_put_validates_locale_and_requires_auth(): void
    {
        $page = $this->createBilingualPage();

        $this->putJson("/api/v1/seo/page/{$page->id}", ['locale' => 'en', 'meta_title' => 'x'])
            ->assertUnauthorized();

        $this->withHeaders($this->adminHeaders())
            ->putJson("/api/v1/seo/page/{$page->id}", ['locale' => 'fr', 'meta_title' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['locale']);

        $this->withHeaders($this->adminHeaders())
            ->putJson("/api/v1/seo/page/{$page->id}", ['locale' => 'en', 'og_image' => 'javascript:alert(1)'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['og_image']);
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    public function test_public_project_seo_resolves_by_x_content_locale(): void
    {
        Project::withoutEvents(static function (): void {
            Project::forceCreate([
                'title' => 'Seo Loc Test',
                'slug' => 'seo-loc-test',
                'summary' => 'Summary',
                'description' => null,
                'content_locale' => 'en',
                'status' => 'published',
                'featured' => false,
                'published_at' => now(),
            ]);
        });

        $project = Project::where('slug', 'seo-loc-test')->firstOrFail();
        $user = User::factory()->create();
        $user->assignRole('editor');
        $headers = $this->bearerHeaders($user);

        $this->withHeaders($headers)
            ->putJson("/api/v1/seo/project/{$project->id}", [
                'locale' => 'en',
                'meta_title' => 'English Meta',
                'meta_description' => 'English desc',
            ])
            ->assertOk();

        $this->withHeaders($headers)
            ->putJson("/api/v1/seo/project/{$project->id}", [
                'locale' => 'ar',
                'meta_title' => 'عنوان عربي',
                'meta_description' => 'وصف',
            ])
            ->assertOk();

        $this->withHeader('X-Content-Locale', 'ar')
            ->getJson('/api/public/projects/seo-loc-test')
            ->assertOk()
            ->assertJsonPath('data.seo_meta.meta_title', 'عنوان عربي')
            ->assertJsonPath('data.seo_meta.locale', 'ar');

        $this->withHeader('X-Content-Locale', 'en')
            ->getJson('/api/public/projects/seo-loc-test')
            ->assertOk()
            ->assertJsonPath('data.seo_meta.meta_title', 'English Meta')
            ->assertJsonPath('data.seo_meta.locale', 'en');
    }

    public function test_put_seo_stores_extended_fields_for_service(): void
    {
        $service = Service::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('editor');
        $headers = $this->bearerHeaders($user);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => 'Custom schema name',
        ];

        $response = $this->withHeaders($headers)
            ->putJson("/api/v1/seo/service/{$service->id}", [
                'locale' => 'en',
                'meta_title' => 'Service meta',
                'meta_description' => 'Service description',
                'canonical_url' => 'https://example.test/services/widget',
                'og_title' => 'OG Title',
                'og_description' => 'OG Desc',
                'og_image' => 'https://example.test/card.png',
                'twitter_card' => 'summary_large_image',
                'schema' => $schema,
            ]);

        $response->assertOk()
            ->assertJsonPath('canonical_url', 'https://example.test/services/widget')
            ->assertJsonPath('twitter_card', 'summary_large_image')
            ->assertJsonPath('locale', 'en');

        $this->assertDatabaseHas('seo_metas', [
            'seoable_type' => Service::class,
            'seoable_id' => $service->id,
            'locale' => 'en',
            'twitter_card' => 'summary_large_image',
        ]);

        $meta = $service->fresh()->seoMetas->firstWhere('locale', 'en');
        $this->assertNotNull($meta);
        $this->assertSame('OG Title', $meta->og_title);
        $this->assertSame('summary_large_image', $meta->twitter_card);
        $this->assertIsArray($meta->schema);
        $this->assertSame('Service', $meta->schema['@type'] ?? null);
    }
}
