<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\User;
use App\Support\ContentExportEnvelope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CategoryExportImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::flush();
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(User $user, string $contentLocale = 'ar'): array
    {
        return [
            'Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken,
            'X-Content-Locale' => $contentLocale,
            'Accept' => 'application/json',
        ];
    }

    public function test_export_requires_content_locale_header(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$admin->createToken('test')->plainTextToken,
            'Accept' => 'application/json',
        ])
            ->get('/api/v1/categories/export')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['X-Content-Locale']);
    }

    public function test_export_with_arabic_locale_only_includes_translated_categories(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $withAr = Category::create([
            'name' => 'English Name',
            'slug' => 'with-ar-translation',
            'content_locale' => null,
            'description' => 'English desc',
        ]);
        CategoryTranslation::create([
            'category_id' => $withAr->id,
            'locale' => 'ar',
            'name' => 'اسم عربي',
            'description' => 'وصف',
        ]);

        Category::create([
            'name' => 'No Arabic',
            'slug' => 'no-arabic-export',
            'content_locale' => null,
            'description' => 'Only default',
        ]);

        $response = $this->withHeaders($this->bearerHeaders($admin, 'ar'))
            ->get('/api/v1/categories/export');

        $response->assertOk();
        $payload = json_decode($response->streamedContent(), true);
        $this->assertSame(ContentExportEnvelope::FORMAT, $payload['format']);
        $this->assertSame('ar', $payload['locale']);
        $slugs = array_column($payload['items'], 'slug');
        $this->assertContains('with-ar-translation', $slugs);
        $this->assertNotContains('no-arabic-export', $slugs);
        $item = collect($payload['items'])->firstWhere('slug', 'with-ar-translation');
        $this->assertSame('اسم عربي', $item['name']);
    }

    public function test_export_with_ids_returns_subset(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $a = Category::create([
            'name' => 'Cat A',
            'slug' => 'export-cat-a',
            'content_locale' => 'en',
            'description' => 'A',
        ]);
        Category::create([
            'name' => 'Cat B',
            'slug' => 'export-cat-b',
            'content_locale' => 'en',
            'description' => 'B',
        ]);

        $response = $this->withHeaders($this->bearerHeaders($admin, 'en'))
            ->get('/api/v1/categories/export?ids[]='.$a->id);

        $response->assertOk();
        $payload = json_decode($response->streamedContent(), true);
        $this->assertCount(1, $payload['items']);
        $this->assertSame('export-cat-a', $payload['items'][0]['slug']);
        $this->assertSame($a->id, $payload['items'][0]['id']);
    }

    public function test_export_items_include_category_id(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $category = Category::create([
            'name' => 'With Id',
            'slug' => 'export-with-id',
            'content_locale' => 'en',
            'description' => 'Desc',
        ]);

        $response = $this->withHeaders($this->bearerHeaders($admin, 'en'))
            ->get('/api/v1/categories/export');

        $response->assertOk();
        $payload = json_decode($response->streamedContent(), true);
        $item = collect($payload['items'])->firstWhere('slug', 'export-with-id');
        $this->assertNotNull($item);
        $this->assertSame($category->id, $item['id']);
    }

    public function test_import_translation_by_id_without_matching_slug(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $category = Category::create([
            'name' => 'Primary EN',
            'slug' => 'real-slug-on-server',
            'content_locale' => 'en',
            'description' => 'EN',
        ]);

        $envelope = ContentExportEnvelope::build('categories', 'ar', [
            [
                'id' => $category->id,
                'name' => 'عربي بالمعرف',
                'description' => 'وصف بالمعرف',
                'is_featured' => false,
            ],
        ]);

        $this->withHeaders($this->bearerHeaders($admin, 'ar'))
            ->postJson('/api/v1/categories/import?mode=translation_only', $envelope)
            ->assertOk()
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('created', 0);

        $this->assertDatabaseHas('category_translations', [
            'category_id' => $category->id,
            'locale' => 'ar',
            'name' => 'عربي بالمعرف',
        ]);
    }

    public function test_import_by_id_rejects_slug_mismatch(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $category = Category::create([
            'name' => 'EN',
            'slug' => 'correct-slug',
            'content_locale' => 'en',
            'description' => 'd',
        ]);

        $envelope = ContentExportEnvelope::build('categories', 'ar', [
            [
                'id' => $category->id,
                'slug' => 'wrong-slug',
                'name' => 'عربي',
                'description' => 'وصف',
                'is_featured' => false,
            ],
        ]);

        $this->withHeaders($this->bearerHeaders($admin, 'ar'))
            ->postJson('/api/v1/categories/import?mode=translation_only', $envelope)
            ->assertOk()
            ->assertJsonPath('skipped', 1)
            ->assertJsonFragment(['message' => 'Slug does not match the record id.']);
    }

    public function test_import_upsert_creates_and_updates_translation(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $existing = Category::create([
            'name' => 'Primary EN',
            'slug' => 'import-existing-en',
            'content_locale' => 'en',
            'description' => 'EN desc',
        ]);

        $envelope = ContentExportEnvelope::build('categories', 'ar', [
            [
                'slug' => 'import-new-ar',
                'name' => 'جديد',
                'description' => 'وصف جديد',
                'is_featured' => true,
            ],
            [
                'slug' => 'import-existing-en',
                'name' => 'عربي محدث',
                'description' => 'وصف عربي',
                'is_featured' => false,
            ],
        ]);

        $response = $this->withHeaders($this->bearerHeaders($admin, 'ar'))
            ->postJson('/api/v1/categories/import', $envelope);

        $response->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 1);

        $this->assertDatabaseHas('categories', ['slug' => 'import-new-ar', 'content_locale' => 'ar']);
        $this->assertDatabaseHas('category_translations', [
            'category_id' => $existing->id,
            'locale' => 'ar',
            'name' => 'عربي محدث',
        ]);
    }

    public function test_import_translation_only_skips_unknown_slugs(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $category = Category::create([
            'name' => 'EN',
            'slug' => 'translation-only-target',
            'content_locale' => 'en',
            'description' => 'd',
        ]);

        $envelope = ContentExportEnvelope::build('categories', 'ar', [
            [
                'slug' => 'translation-only-target',
                'name' => 'ترجمة',
                'description' => 'وصف',
                'is_featured' => false,
            ],
            [
                'slug' => 'missing-slug-xyz',
                'name' => 'لا يوجد',
                'description' => 'x',
                'is_featured' => false,
            ],
        ]);

        $response = $this->withHeaders($this->bearerHeaders($admin, 'ar'))
            ->postJson('/api/v1/categories/import?mode=translation_only', $envelope);

        $response->assertOk()
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('created', 0)
            ->assertJsonPath('skipped', 1);

        $this->assertDatabaseMissing('categories', ['slug' => 'missing-slug-xyz']);
        $this->assertDatabaseHas('category_translations', [
            'category_id' => $category->id,
            'locale' => 'ar',
            'name' => 'ترجمة',
        ]);
    }

    public function test_import_invalid_envelope_returns_422(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $this->withHeaders($this->bearerHeaders($admin, 'ar'))
            ->postJson('/api/v1/categories/import', [
                'format' => 'wrong',
                'version' => 1,
                'entity' => 'categories',
                'locale' => 'ar',
                'items' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['format']);
    }

    public function test_store_generates_slug_when_missing(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$admin->createToken('test')->plainTextToken,
            'Accept' => 'application/json',
        ])
            ->postJson('/api/v1/categories', [
                'name' => 'New Test Category',
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'new-test-category');

        $id = $response->json('data.id');
        $this->assertNotNull($id);
        $this->assertDatabaseHas('categories', [
            'id' => $id,
            'name' => 'New Test Category',
            'slug' => 'new-test-category',
        ]);
    }
}
