<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Support\ContentExportEnvelope;
use App\Support\ProjectSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContentImportCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ProjectSettingsStore::save([
            'default_locale' => 'en',
            'site_languages' => [
                ['code' => 'en', 'label' => 'English', 'native_label' => 'English', 'rtl' => false],
                ['code' => 'ar', 'label' => 'Arabic', 'native_label' => 'العربية', 'rtl' => true],
            ],
        ]);
    }

    public function test_translation_only_category_import_refreshes_public_category_list(): void
    {
        $category = Category::query()->create(['slug' => 'web', 'name' => 'Web']);
        $category->translations()->create(['locale' => 'ar', 'name' => 'قديم']);
        Project::query()->create(['slug' => 'site', 'title' => 'Site', 'status' => 'published'])
            ->categories()->attach($category->id);
        $this->publicList('/api/public/categories')->assertJsonPath('data.0.name', 'قديم');

        $this->importArabic('categories', ['id' => $category->id, 'name' => 'جديد']);

        $this->publicList('/api/public/categories')->assertJsonPath('data.0.name', 'جديد');
    }

    public function test_translation_only_service_import_refreshes_public_list(): void
    {
        $service = Service::query()->create(['slug' => 'web', 'name' => 'Web', 'is_published' => true]);
        $service->translations()->create(['locale' => 'ar', 'name' => 'قديم']);
        $this->publicList('/api/public/services')->assertJsonPath('data.0.name', 'قديم');

        $this->importArabic('services', ['id' => $service->id, 'name' => 'جديد']);

        $this->publicList('/api/public/services')->assertJsonPath('data.0.name', 'جديد');
    }

    public function test_translation_only_project_import_refreshes_public_list(): void
    {
        $project = Project::query()->create(['slug' => 'site', 'title' => 'Site', 'status' => 'published']);
        $project->translations()->create(['locale' => 'ar', 'title' => 'قديم']);
        $this->publicList('/api/public/projects')->assertJsonPath('data.0.title', 'قديم');

        $this->importArabic('projects', ['id' => $project->id, 'title' => 'جديد']);

        $this->publicList('/api/public/projects')->assertJsonPath('data.0.title', 'جديد');
    }

    private function publicList(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('X-Content-Locale', 'ar')->getJson($uri)->assertOk();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importArabic(string $entity, array $row): void
    {
        Role::findOrCreate('admin');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $this->withHeader('X-Content-Locale', 'ar')
            ->postJson('/api/v1/'.$entity.'/import?mode=translation_only', ContentExportEnvelope::build($entity, 'ar', [$row]))
            ->assertOk()
            ->assertJsonPath('updated', 1);
    }
}
