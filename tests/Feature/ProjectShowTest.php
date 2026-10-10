<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProjectShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::forget('project-settings');
    }

    public function test_admin_can_load_a_project_with_testimonials_for_editing(): void
    {
        $project = Project::withoutEvents(static fn () => Project::forceCreate([
            'title' => 'Shop',
            'slug' => 'shop-show-test',
            'summary' => 'Summary',
            'content_locale' => 'en',
            'status' => 'draft',
            'featured' => false,
        ]));
        Testimonial::withoutEvents(static fn () => Testimonial::forceCreate([
            'project_id' => $project->id,
            'client_name' => 'Client',
            'content' => 'Great work',
            'rating' => 5,
        ]));

        $admin = User::query()->where('email', 'admin@credocode.test')->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin->createToken('test')->plainTextToken])
            ->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Shop')
            ->assertJsonPath('data.slug', 'shop-show-test')
            ->assertJsonPath('data.testimonials.0.client_name', 'Client');
    }
}
