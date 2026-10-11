<?php

namespace Tests\Feature;

use App\Models\AppMedia;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::forget('project-settings');
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::query()->where('email', 'admin@credocode.test')->firstOrFail());
    }

    private function media(string $name, string $mime = 'image/jpeg'): AppMedia
    {
        return AppMedia::query()->create([
            'name' => $name,
            'file_name' => $name,
            'mime_type' => $mime,
            'disk' => 'public',
            'size' => 1,
            'collection_name' => 'library',
            'model_type' => 'library',
            'model_id' => 0,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);
    }

    private function project(): Project
    {
        return Project::query()->create(['title' => 'Shop', 'slug' => 'gallery-api-shop', 'status' => 'draft']);
    }

    public function test_admin_saves_an_ordered_gallery_and_gets_thumbnails_back(): void
    {
        $this->actingAsAdmin();
        $a = $this->media('a.jpg');
        $b = $this->media('b.png', 'image/png');
        $project = $this->project();

        $this->putJson("/api/v1/projects/{$project->id}", ['gallery_media_ids' => [$b->id, $a->id]])
            ->assertOk()
            ->assertJsonPath('data.gallery_media_ids', [$b->id, $a->id])
            ->assertJsonPath('data.gallery_items.0.id', $b->id)
            ->assertJsonPath('data.gallery_items.1.name', 'a.jpg');

        $this->assertSame([$b->id, $a->id], $project->fresh()->galleryMediaIds());

        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.gallery_items');
    }

    public function test_admin_can_create_a_project_with_a_gallery_and_clear_it(): void
    {
        $this->actingAsAdmin();
        $a = $this->media('a.jpg');

        $id = $this->postJson('/api/v1/projects', ['title' => 'New', 'status' => 'draft', 'gallery_media_ids' => [$a->id]])
            ->assertCreated()
            ->json('data.id');
        $this->assertSame([$a->id], Project::query()->findOrFail($id)->galleryMediaIds());

        $this->putJson("/api/v1/projects/{$id}", ['gallery_media_ids' => []])->assertOk();
        $this->assertSame([], Project::query()->findOrFail($id)->galleryMediaIds());
    }

    public function test_gallery_rejects_non_images_missing_ids_and_duplicates(): void
    {
        $this->actingAsAdmin();
        $image = $this->media('a.jpg');
        $pdf = $this->media('doc.pdf', 'application/pdf');
        $project = $this->project();

        $this->putJson("/api/v1/projects/{$project->id}", ['gallery_media_ids' => [$pdf->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('gallery_media_ids.0');
        $this->putJson("/api/v1/projects/{$project->id}", ['gallery_media_ids' => [999999]])
            ->assertUnprocessable()->assertJsonValidationErrors('gallery_media_ids.0');
        $this->putJson("/api/v1/projects/{$project->id}", ['gallery_media_ids' => [$image->id, $image->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('gallery_media_ids.0');
        $this->putJson("/api/v1/projects/{$project->id}", ['gallery_media_ids' => 'nope'])
            ->assertUnprocessable()->assertJsonValidationErrors('gallery_media_ids');
    }

    public function test_user_without_rights_cannot_change_the_gallery(): void
    {
        Role::findOrCreate('user');
        $user = User::factory()->create();
        $user->assignRole('user');
        Sanctum::actingAs($user);
        $project = $this->project();

        $this->putJson("/api/v1/projects/{$project->id}", ['gallery_media_ids' => [$this->media('a.jpg')->id]])
            ->assertForbidden();
        $this->assertSame([], $project->fresh()->galleryMediaIds());
    }
}
