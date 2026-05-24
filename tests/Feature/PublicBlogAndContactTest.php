<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBlogAndContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_public_blog_lists_published_posts_only(): void
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        BlogPost::query()->create([
            'title' => 'Published Post',
            'slug' => 'published-post',
            'excerpt' => 'Excerpt',
            'content' => '<p>Body</p>',
            'status' => 'published',
            'featured' => false,
            'author_id' => $admin->id,
            'published_at' => now(),
        ]);

        BlogPost::query()->create([
            'title' => 'Draft Post',
            'slug' => 'draft-post',
            'excerpt' => 'Excerpt',
            'content' => '<p>Body</p>',
            'status' => 'draft',
            'featured' => false,
            'author_id' => $admin->id,
        ]);

        $this->getJson('/api/public/blog-posts')
            ->assertOk();

        $this->getJson('/api/public/blog-posts/published-post')
            ->assertOk()
            ->assertJsonPath('data.slug', 'published-post');

        $this->getJson('/api/public/blog-posts/draft-post')
            ->assertNotFound();
    }

    public function test_contact_form_accepts_submission(): void
    {
        $this->postJson('/api/contact', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'Hello',
            'message' => 'Test message body',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_public_site_content_returns_payload(): void
    {
        $this->getJson('/api/public/site-content')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['pricingTiers', 'faq', 'contact', 'about', 'techStack'],
            ]);
    }
}
