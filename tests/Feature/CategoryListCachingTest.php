<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryListCachingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_list_drops_a_category_when_its_only_project_is_unpublished(): void
    {
        $category = Category::query()->create(['slug' => 'web', 'name' => 'Web']);
        $project = Project::query()->create(['slug' => 'site', 'title' => 'Site', 'status' => 'published']);
        $project->categories()->attach($category->id);
        $this->getJson('/api/public/categories')->assertOk()->assertJsonCount(1, 'data');

        $project->update(['status' => 'draft']);

        $this->getJson('/api/public/categories')->assertJsonCount(0, 'data');
    }

    public function test_admin_project_counts_follow_category_assignment(): void
    {
        $category = Category::query()->create(['slug' => 'web', 'name' => 'Web']);
        $project = Project::query()->create(['slug' => 'site', 'title' => 'Site', 'status' => 'published']);
        $this->actingAdmin();
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonPath('data.0.projects_count', 0);

        $this->putJson('/api/v1/projects/'.$project->id, ['category_ids' => [$category->id]])->assertOk();

        $this->getJson('/api/v1/categories')->assertJsonPath('data.0.projects_count', 1);
    }

    private function actingAdmin(): void
    {
        Role::findOrCreate('admin');
        $user = User::factory()->create();
        $user->assignRole('admin');
        Sanctum::actingAs($user);
    }
}
