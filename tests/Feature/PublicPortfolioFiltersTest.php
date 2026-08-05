<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Project;
use App\Services\ContentExport\CategoryListCacheInvalidator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicPortfolioFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        CategoryListCacheInvalidator::flush();
        Cache::flush();
    }

    public function test_public_categories_lists_only_categories_with_published_projects(): void
    {
        $withProjects = Category::query()->create([
            'name' => 'Web Apps',
            'slug' => 'web-apps',
            'content_locale' => null,
            'is_featured' => false,
        ]);
        Category::query()->create([
            'name' => 'Unused',
            'slug' => 'unused-cat',
            'content_locale' => null,
            'is_featured' => false,
        ]);

        $project = Project::withoutEvents(static function () {
            return Project::forceCreate([
                'title' => 'Alpha',
                'slug' => 'alpha-portfolio',
                'summary' => 'Summary',
                'description' => null,
                'status' => 'published',
                'featured' => false,
                'published_at' => now(),
            ]);
        });
        $withProjects->projects()->attach($project->id);

        CategoryListCacheInvalidator::flush();
        Cache::flush();

        $res = $this->getJson('/api/public/categories', ['X-Content-Locale' => 'en']);
        $res->assertOk();
        $slugs = collect($res->json('data'))->pluck('slug')->all();
        $this->assertContains('web-apps', $slugs);
        $this->assertNotContains('unused-cat', $slugs);

        $web = collect($res->json('data'))->firstWhere('slug', 'web-apps');
        $this->assertSame(1, (int) ($web['projects_count'] ?? 0));
    }

    public function test_public_categories_applies_arabic_locale_without_english_fallback(): void
    {
        $category = Category::query()->create([
            'name' => 'Mobile',
            'slug' => 'mobile',
            'content_locale' => null,
            'is_featured' => false,
        ]);
        CategoryTranslation::query()->create([
            'category_id' => $category->id,
            'locale' => 'ar',
            'name' => 'موبايل',
            'description' => null,
        ]);

        $project = Project::withoutEvents(static function () {
            return Project::forceCreate([
                'title' => 'Beta',
                'slug' => 'beta-portfolio',
                'summary' => 'Summary',
                'description' => null,
                'status' => 'published',
                'featured' => false,
                'published_at' => now(),
            ]);
        });
        $category->projects()->attach($project->id);
        $project->translations()->create([
            'locale' => 'ar',
            'title' => 'بيتا',
            'summary' => 'ملخص',
            'description' => null,
        ]);

        CategoryListCacheInvalidator::flush();
        Cache::flush();

        $ar = $this->getJson('/api/public/categories', ['X-Content-Locale' => 'ar']);
        $ar->assertOk();
        $row = collect($ar->json('data'))->firstWhere('slug', 'mobile');
        $this->assertNotNull($row);
        $this->assertSame('موبايل', $row['name'] ?? null);
    }

    public function test_public_projects_paginate_and_filter_by_category_slug(): void
    {
        $cat = Category::query()->create([
            'name' => 'Brand',
            'slug' => 'brand',
            'content_locale' => null,
            'is_featured' => false,
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $project = Project::withoutEvents(static function () use ($i) {
                return Project::forceCreate([
                    'title' => "Project {$i}",
                    'slug' => "project-{$i}",
                    'summary' => "Summary {$i}",
                    'description' => null,
                    'status' => 'published',
                    'featured' => false,
                    'published_at' => now()->subDays($i),
                ]);
            });
            if ($i <= 2) {
                $cat->projects()->attach($project->id);
            }
        }

        Cache::flush();

        $page1 = $this->getJson('/api/public/projects?per_page=2&page=1&category_slug=brand', [
            'X-Content-Locale' => 'en',
        ]);
        $page1->assertOk();
        $this->assertCount(2, $page1->json('data'));
        $this->assertSame(2, (int) $page1->json('meta.per_page'));
        $this->assertSame(2, (int) $page1->json('meta.total'));

        $page2 = $this->getJson('/api/public/projects?per_page=2&page=2&category_slug=brand', [
            'X-Content-Locale' => 'en',
        ]);
        $page2->assertOk();
        $this->assertCount(0, $page2->json('data'));
    }
}
