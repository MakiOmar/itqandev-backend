<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoMetaMultilingualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
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
