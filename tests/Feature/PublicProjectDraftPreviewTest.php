<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProjectDraftPreviewTest extends TestCase
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

    public function test_guest_cannot_resolve_draft_public_project(): void
    {
        Project::withoutEvents(static function (): void {
            Project::forceCreate([
                'title' => 'Draft Preview',
                'slug' => 'draft-only-project',
                'summary' => 'Summary',
                'description' => null,
                'status' => 'draft',
                'featured' => false,
                'published_at' => null,
            ]);
        });

        $this->getJson('/api/public/projects/draft-only-project')->assertStatus(404);
    }

    public function test_editor_can_resolve_draft_public_project_with_bearer_token(): void
    {
        Project::withoutEvents(static function (): void {
            Project::forceCreate([
                'title' => 'Draft Preview',
                'slug' => 'staff-draft-visible',
                'summary' => 'Summary',
                'description' => null,
                'status' => 'draft',
                'featured' => false,
                'published_at' => null,
            ]);
        });

        $user = User::factory()->create();
        $user->assignRole('editor');

        $this->withHeaders($this->bearerHeaders($user))
            ->getJson('/api/public/projects/staff-draft-visible')
            ->assertOk()
            ->assertJsonPath('data.slug', 'staff-draft-visible')
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_guest_sees_published_same_route(): void
    {
        Project::withoutEvents(static function (): void {
            Project::forceCreate([
                'title' => 'Live',
                'slug' => 'live-project',
                'summary' => 'Summary',
                'description' => null,
                'status' => 'published',
                'featured' => false,
                'published_at' => now(),
            ]);
        });

        $this->getJson('/api/public/projects/live-project')
            ->assertOk()
            ->assertJsonPath('data.slug', 'live-project')
            ->assertJsonPath('data.status', 'published');
    }
}
