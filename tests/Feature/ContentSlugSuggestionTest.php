<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentSlugSuggestionTest extends TestCase
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

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/content-slugs/suggest', [
            'entity' => 'projects',
            'source' => 'Hello',
        ])->assertUnauthorized();
    }

    public function test_returns_unique_slug_with_numeric_suffix_when_taken(): void
    {
        $user = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($user);

        Project::create([
            'title' => 'Hello World',
            'slug' => 'hello-world',
            'status' => 'draft',
        ]);

        $this->withHeaders($this->bearerHeaders($user))
            ->postJson('/api/v1/content-slugs/suggest', [
                'entity' => 'projects',
                'source' => 'Hello World',
            ])
            ->assertOk()
            ->assertJson(['slug' => 'hello-world-2']);
    }

    public function test_ignore_id_allows_keeping_same_slug(): void
    {
        $user = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($user);

        $project = Project::create([
            'title' => 'A',
            'slug' => 'my-slug',
            'status' => 'draft',
        ]);

        $this->withHeaders($this->bearerHeaders($user))
            ->postJson('/api/v1/content-slugs/suggest', [
                'entity' => 'projects',
                'source' => 'my slug',
                'ignore_id' => $project->id,
            ])
            ->assertOk()
            ->assertJson(['slug' => 'my-slug']);
    }
}
