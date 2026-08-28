<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProjectCardResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_public_project_list_includes_nested_categories(): void
    {
        $category = Category::withoutEvents(static function (): Category {
            return Category::forceCreate([
                'name' => 'Web Apps',
                'slug' => 'web-apps',
                'description' => null,
                'is_featured' => false,
            ]);
        });

        Project::withoutEvents(static function () use ($category): void {
            $project = Project::forceCreate([
                'title' => 'Portfolio Card Test',
                'slug' => 'portfolio-card-test',
                'summary' => 'Summary',
                'description' => null,
                'status' => 'published',
                'featured' => true,
                'published_at' => now(),
            ]);
            $project->categories()->sync([$category->id]);
        });

        $response = $this->getJson('/api/public/projects?per_page=12');

        $response->assertOk();

        $rows = $response->json('data');
        $this->assertIsArray($rows);

        $match = collect($rows)->firstWhere('slug', 'portfolio-card-test');
        $this->assertNotNull($match);
        $this->assertArrayHasKey('categories', $match);
        $this->assertCount(1, $match['categories']);
        $this->assertSame('web-apps', $match['categories'][0]['slug']);
        $this->assertSame('Web Apps', $match['categories'][0]['name']);
        $this->assertContains('Web Apps', $match['tags']);
    }
}
