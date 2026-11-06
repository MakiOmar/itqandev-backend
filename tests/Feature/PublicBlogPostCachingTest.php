<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\BlogPostTranslation;
use App\Models\User;
use App\Support\ProjectSettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CountsTableQueries;
use Tests\TestCase;

class PublicBlogPostCachingTest extends TestCase
{
    use CountsTableQueries, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ProjectSettingsStore::save([
            'default_locale' => 'en',
            'site_languages' => [
                ['code' => 'en', 'label' => 'English', 'native_label' => 'English', 'rtl' => false],
                ['code' => 'ar', 'label' => 'Arabic', 'native_label' => 'العربية', 'rtl' => true],
            ],
        ]);
    }

    public function test_empty_translation_rows_do_not_count_as_locale_content(): void
    {
        $translated = $this->publishedPost('translated');
        BlogPostTranslation::query()->create(['blog_post_id' => $translated->id, 'locale' => 'ar', 'title' => 'مترجم']);
        $empty = $this->publishedPost('empty-translation');
        BlogPostTranslation::query()->create(['blog_post_id' => $empty->id, 'locale' => 'ar', 'title' => '', 'excerpt' => null]);

        $this->withHeader('X-Content-Locale', 'ar')
            ->getJson('/api/public/blog-posts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'translated')
            ->assertJsonPath('data.0.title', 'مترجم')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_page_parameter_is_validated(): void
    {
        $this->getJson('/api/public/blog-posts?page=abc')->assertUnprocessable();
        $this->getJson('/api/public/blog-posts?page=0')->assertUnprocessable();
        $this->getJson('/api/public/blog-posts?page=100000')->assertUnprocessable();
        $this->getJson('/api/public/blog-posts?page=2')->assertOk();
    }

    public function test_list_reflects_edits_without_waiting_for_ttl(): void
    {
        $post = $this->publishedPost('launch-notes');
        $this->getJson('/api/public/blog-posts')->assertJsonPath('data.0.title', 'Launch-notes');

        $post->update(['title' => 'Launch notes v2']);

        $this->getJson('/api/public/blog-posts')->assertJsonPath('data.0.title', 'Launch notes v2');
    }

    public function test_translation_removed_through_admin_drops_post_from_locale_list(): void
    {
        $post = $this->publishedPost('bilingual');
        BlogPostTranslation::query()->create(['blog_post_id' => $post->id, 'locale' => 'ar', 'title' => 'ثنائي']);
        $this->withHeader('X-Content-Locale', 'ar')->getJson('/api/public/blog-posts')->assertJsonCount(1, 'data');

        $this->actingEditor();
        $this->putJson('/api/v1/blog-posts/'.$post->id, [
            'translations' => [['locale' => 'ar', 'title' => '', 'excerpt' => '', 'content' => '']],
        ])->assertOk();
        $this->assertSame(0, $post->translations()->count());

        $this->withHeader('X-Content-Locale', 'ar')->getJson('/api/public/blog-posts')->assertJsonCount(0, 'data');
    }

    public function test_seo_edit_shows_on_cached_post(): void
    {
        $post = $this->publishedPost('seo-target');
        $this->getJson('/api/public/blog-posts/seo-target')->assertOk()->assertJsonPath('data.seo_meta', null);

        $this->actingEditor();
        $this->putJson('/api/v1/seo/blog-post/'.$post->id, ['locale' => 'en', 'meta_title' => 'Fresh title'])->assertOk();

        $this->getJson('/api/public/blog-posts/seo-target')->assertOk()->assertJsonPath('data.seo_meta.meta_title', 'Fresh title');
    }

    public function test_media_queries_do_not_grow_with_rows(): void
    {
        $this->publishedPost('one');
        $few = $this->queriesAgainst('media', fn () => $this->getJson('/api/public/blog-posts')->assertOk());

        foreach (range(2, 6) as $n) {
            $this->publishedPost('post-'.$n);
        }
        $many = $this->queriesAgainst('media', fn () => $this->getJson('/api/public/blog-posts?per_page=24')
            ->assertOk()
            ->assertJsonCount(6, 'data'));

        $this->assertSame($few, $many);
    }

    public function test_show_is_cached_including_not_found_and_mixed_case(): void
    {
        $this->publishedPost('hello-world');
        $this->getJson('/api/public/blog-posts/hello-world')->assertOk()->assertJsonPath('data.slug', 'hello-world');

        $this->assertSame(0, $this->queriesAgainst('blog_posts', fn () => $this->getJson('/api/public/blog-posts/Hello-World')
            ->assertOk()
            ->assertJsonPath('data.slug', 'hello-world')));

        $this->getJson('/api/public/blog-posts/missing-post')->assertNotFound();
        $this->assertSame(0, $this->queriesAgainst('blog_posts', fn () => $this->getJson('/api/public/blog-posts/missing-post')
            ->assertNotFound()));
    }

    private function publishedPost(string $slug): BlogPost
    {
        return BlogPost::query()->create([
            'title' => ucfirst($slug),
            'slug' => $slug,
            'excerpt' => 'Excerpt',
            'content' => '<p>Body</p>',
            'status' => 'published',
            'featured' => false,
            'author_id' => User::factory()->create()->id,
            'published_at' => now(),
        ]);
    }

    private function actingEditor(): void
    {
        Role::findOrCreate('editor');
        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);
    }
}
