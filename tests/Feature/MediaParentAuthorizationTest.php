<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaParentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    public function test_viewer_cannot_attach_media_to_project(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $project = Project::create([
            'title' => 'Restricted project',
            'slug' => 'restricted-project',
            'status' => 'draft',
        ]);

        $this->withHeaders($this->bearerHeaders($viewer))
            ->post("/api/v1/media/project/{$project->id}/hero", [
                'file' => UploadedFile::fake()->image('hero.jpg'),
            ])
            ->assertForbidden();
    }
}
