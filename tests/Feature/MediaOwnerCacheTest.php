<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use App\Support\ProjectSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaOwnerCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        ProjectSettingsStore::save([
            'default_locale' => 'en',
            'site_languages' => [
                ['code' => 'en', 'label' => 'English', 'native_label' => 'English', 'rtl' => false],
            ],
        ]);
    }

    public function test_attaching_and_removing_media_refreshes_the_owner_payload(): void
    {
        $post = BlogPost::query()->create([
            'title' => 'Cover story',
            'slug' => 'cover-story',
            'content' => '<p>Body</p>',
            'status' => 'published',
            'author_id' => User::factory()->create()->id,
            'published_at' => now(),
        ]);
        $this->getJson('/api/public/blog-posts/cover-story')->assertOk()->assertJsonPath('data.cover_image', null);

        $media = $post->addMediaFromString('cover')->usingFileName('cover.txt')->toMediaCollection('featured_image');

        $cover = $this->getJson('/api/public/blog-posts/cover-story')->assertOk()->json('data.cover_image');
        $this->assertIsString($cover);
        $this->assertStringContainsString('cover.txt', $cover);

        $media->delete();

        $this->getJson('/api/public/blog-posts/cover-story')->assertOk()->assertJsonPath('data.cover_image', null);
    }
}
