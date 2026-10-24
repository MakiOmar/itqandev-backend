<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogPostCreateTest extends TestCase
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
    private function editorHeaders(): array
    {
        $user = User::factory()->create();
        $user->assignRole('editor');

        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    public function test_post_without_content_is_created(): void
    {
        $response = $this->withHeaders($this->editorHeaders())
            ->postJson('/api/v1/blog-posts', [
                'title' => 'Title only draft',
                'slug' => 'title-only-draft',
                'status' => 'draft',
            ])
            ->assertCreated()
            ->assertJsonPath('slug', 'title-only-draft');

        $this->assertNull(BlogPost::query()->findOrFail($response->json('id'))->content);
    }

    public function test_content_can_be_cleared_on_update(): void
    {
        $headers = $this->editorHeaders();
        $id = $this->withHeaders($headers)
            ->postJson('/api/v1/blog-posts', [
                'title' => 'With body',
                'slug' => 'with-body',
                'status' => 'draft',
                'content' => '<p>Body</p>',
            ])
            ->assertCreated()
            ->json('id');

        $this->withHeaders($headers)
            ->putJson("/api/v1/blog-posts/{$id}", ['content' => null])
            ->assertOk();

        $this->assertNull(BlogPost::query()->findOrFail($id)->content);
    }

    public function test_create_requires_title_and_auth(): void
    {
        $this->postJson('/api/v1/blog-posts', ['title' => 'x', 'status' => 'draft'])
            ->assertUnauthorized();

        $this->withHeaders($this->editorHeaders())
            ->postJson('/api/v1/blog-posts', ['status' => 'draft'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }
}
