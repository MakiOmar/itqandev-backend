<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\BlogPostTranslation;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Page;
use App\Models\Project;
use App\Models\ProjectTranslation;
use App\Models\Service;
use App\Models\Skill;
use App\Models\Testimonial;
use App\Models\TestimonialTranslation;
use App\Models\ThemeTemplate;
use App\Models\User;
use App\Services\Appearance\ChromeLayoutResolver;
use App\Services\Appearance\ChromeLayoutService;
use App\Services\Appearance\DynamicTagRegistry;
use App\Services\Appearance\DynamicTagResolver;
use App\Services\Appearance\LoopQueryService;
use App\Services\Appearance\ThemeTemplateConditions;
use App\Services\Appearance\ThemeTemplateService;
use App\Services\ContentTypes\ContentTypeRegistry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class ContentTypeRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::forget('project-settings');
    }

    private function registry(): ContentTypeRegistry
    {
        return app(ContentTypeRegistry::class);
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@credocode.test')->firstOrFail();
    }

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    private function translatedProject(): Project
    {
        $project = Project::query()->create([
            'title' => 'Shop build',
            'slug' => 'registry-shop-build',
            'summary' => 'A shop',
            'description' => '<p>Body</p>',
            'status' => 'published',
            'link_url' => 'https://shop.test',
            'demo_url' => 'https://demo.shop.test',
            'featured' => true,
            'published_at' => '2025-04-01 10:00:00',
        ]);
        ProjectTranslation::query()->create([
            'project_id' => $project->id, 'locale' => 'ar', 'title' => 'متجر', 'summary' => 'ملخص', 'description' => '<p>محتوى</p>',
        ]);

        $category = Category::query()->create(['name' => 'E-commerce', 'slug' => 'registry-ecommerce', 'description' => 'Shops']);
        CategoryTranslation::query()->create(['category_id' => $category->id, 'locale' => 'ar', 'name' => 'متاجر', 'description' => 'متاجر إلكترونية']);
        $skill = Skill::query()->create(['name' => 'Laravel', 'slug' => 'registry-laravel']);
        $project->categories()->attach($category->id);
        $project->skills()->attach($skill->id);

        Testimonial::query()->create(['project_id' => $project->id, 'client_name' => 'Hidden', 'content' => 'Unapproved', 'approved' => false]);
        $approved = Testimonial::query()->create([
            'project_id' => $project->id, 'client_name' => 'Sara', 'client_role' => 'Owner', 'company' => 'Shop', 'content' => 'Great work', 'approved' => true,
        ]);
        TestimonialTranslation::query()->create(['testimonial_id' => $approved->id, 'locale' => 'ar', 'client_name' => 'سارة', 'content' => 'عمل رائع']);

        return $project->fresh();
    }

    public function test_registry_lists_configured_types_in_order(): void
    {
        $this->assertSame(['page', 'project', 'blog_post', 'service'], $this->registry()->keys());
        $this->assertSame(['homepage', 'page', 'project', 'blog_post', 'service'], ChromeLayoutService::contentTypes());
        $this->assertSame(['portfolio_index', 'blog_index', 'services_index'], $this->registry()->archiveContexts());
    }

    public function test_disabled_module_removes_type_tags_and_archive(): void
    {
        config(['features.modules.blog' => false]);

        $this->assertNull($this->registry()->get('blog_post'));
        $this->assertNull($this->registry()->forLoopSource('blog'));
        $this->assertNotContains('blog_index', $this->registry()->archiveContexts());
        $this->assertFalse(DynamicTagRegistry::isAllowed('blog_post.author.name'));
        $this->assertTrue(DynamicTagRegistry::isAllowed('project.demo_url'));
        $this->assertContains('blog', array_column($this->registry()->loopSourceOptions(), 'value'));
        $this->assertSame([], LoopQueryService::items(['source' => 'blog']));
    }

    public function test_invalid_config_entry_fails_loudly(): void
    {
        config(['content-types.types' => [\stdClass::class]]);
        $this->app->forgetInstance(ContentTypeRegistry::class);

        $this->expectException(InvalidArgumentException::class);
        $this->registry()->all();
    }

    public function test_tag_ids_are_unique_and_post_ids_kept(): void
    {
        $ids = DynamicTagRegistry::ids();
        $this->assertSame($ids, array_values(array_unique($ids)));
        foreach (['post.title', 'post.subtitle', 'post.excerpt', 'post.content', 'post.url', 'post.image', 'post.date', 'post.terms', 'archive.title', 'site.name'] as $id) {
            $this->assertTrue(DynamicTagRegistry::isAllowed($id), $id);
        }
        $groups = array_column(DynamicTagRegistry::groups(), 'parent', 'id');
        $this->assertSame('project', $groups['project.categories']);
        $this->assertNull($groups['project']);
    }

    public function test_project_values_in_primary_locale_with_relations(): void
    {
        $values = DynamicTagResolver::payloadFromModel($this->translatedProject(), 'project');

        $this->assertSame('Shop build', $values['post.title']);
        $this->assertSame('A shop', $values['post.excerpt']);
        $this->assertSame('<p>Body</p>', $values['post.content']);
        $this->assertSame('/work/registry-shop-build', $values['post.url']);
        $this->assertSame('2025-04-01', $values['post.date']);
        $this->assertSame('E-commerce', $values['post.terms']);
        $this->assertSame('A shop', $values['project.summary']);
        $this->assertSame('<p>Body</p>', $values['project.description']);
        $this->assertSame('https://demo.shop.test', $values['project.demo_url']);
        $this->assertSame('1', $values['project.featured']);
        $this->assertSame('1', $values['project.categories.count']);
        $this->assertSame('/portfolio?category_slug=registry-ecommerce', $values['project.categories.first.url']);
        $this->assertSame('Laravel', $values['project.skills']);
        $this->assertSame('/portfolio?skill_slug=registry-laravel', $values['project.skills.first.url']);
        $this->assertSame('1', $values['project.testimonials.count']);
        $this->assertSame('Great work', $values['project.testimonials.first.quote']);
        $this->assertSame('Sara', $values['project.testimonials.first.author']);
    }

    public function test_project_values_are_localized_including_relations(): void
    {
        $project = $this->translatedProject();
        $values = DynamicTagResolver::payloadFromModel($project, 'project', 'ar');

        $this->assertSame('متجر', $values['post.title']);
        $this->assertSame('ملخص', $values['post.excerpt']);
        $this->assertSame('ملخص', $values['project.summary']);
        $this->assertSame('متاجر', $values['project.categories.first.name']);
        $this->assertSame('متاجر', $values['post.terms']);
        $this->assertSame('عمل رائع', $values['project.testimonials.first.quote']);
        $this->assertSame('سارة', $values['project.testimonials.first.author']);
        // The caller's model is not mutated by localisation.
        $this->assertSame('Shop build', $project->title);
    }

    public function test_empty_relations_give_empty_strings(): void
    {
        $project = Project::query()->create(['title' => 'Bare', 'slug' => 'registry-bare', 'status' => 'published']);
        $values = DynamicTagResolver::payloadFromModel($project, 'project');

        $this->assertSame('', $values['project.categories']);
        $this->assertSame('0', $values['project.categories.count']);
        $this->assertSame('', $values['project.categories.first.name']);
        $this->assertSame('', $values['project.testimonials.first.quote']);
        $this->assertSame('', $values['post.terms']);
        $this->assertSame('', DynamicTagResolver::replaceString('{{project.skills.first.url}}', $values));
    }

    public function test_post_tags_match_previous_payload_for_each_type(): void
    {
        $author = User::factory()->create(['name' => 'Mona']);
        $post = BlogPost::query()->create([
            'title' => 'Hello', 'slug' => 'registry-hello', 'excerpt' => 'Short', 'content' => '<p>Long</p>',
            'status' => 'published', 'author_id' => $author->id, 'published_at' => '2025-02-03 08:00:00',
        ]);
        BlogPostTranslation::query()->create(['blog_post_id' => $post->id, 'locale' => 'ar', 'title' => 'مرحبا']);
        $service = Service::query()->create([
            'name' => 'Web', 'slug' => 'registry-web', 'short_description' => 'Sites', 'description' => 'Full sites', 'icon' => 'globe', 'is_published' => true,
        ]);
        $parent = Page::query()->create(['title' => 'About', 'slug' => 'registry-about', 'status' => Page::STATUS_PUBLISHED]);
        $page = Page::query()->create([
            'title' => 'Team', 'subtitle' => 'Who we are', 'excerpt' => 'People', 'slug' => 'registry-team',
            'status' => Page::STATUS_PUBLISHED, 'parent_id' => $parent->id,
        ]);

        $blog = DynamicTagResolver::payloadFromModel($post, 'blog_post');
        $this->assertSame(['Hello', 'Short', '<p>Long</p>', '/blog/registry-hello', '2025-02-03'], [
            $blog['post.title'], $blog['post.excerpt'], $blog['post.content'], $blog['post.url'], $blog['post.date'],
        ]);
        $this->assertSame('Mona', $blog['blog_post.author.name']);
        $this->assertSame('مرحبا', DynamicTagResolver::payloadFromModel($post, 'blog_post', 'ar')['post.title']);

        $svc = DynamicTagResolver::payloadFromModel($service, 'service');
        $this->assertSame(['Web', 'Sites', 'Full sites', '/services/registry-web', 'globe', ''], [
            $svc['post.title'], $svc['post.excerpt'], $svc['post.content'], $svc['post.url'], $svc['post.image'], $svc['post.date'],
        ]);
        $this->assertSame('globe', $svc['service.icon']);

        $pg = DynamicTagResolver::payloadFromModel($page, 'page');
        $this->assertSame(['Team', 'Who we are', 'People', '/pages/registry-team'], [
            $pg['post.title'], $pg['post.subtitle'], $pg['post.excerpt'], $pg['post.url'],
        ]);
        $this->assertSame('About', $pg['page.parent.title']);
        $this->assertSame('/pages/registry-about', $pg['page.parent.url']);
        $this->assertSame('Team', DynamicTagResolver::resolve('archive.title', $pg));
    }

    public function test_archive_payload_uses_type_archive_label(): void
    {
        $this->assertSame('Portfolio', DynamicTagResolver::payloadFromModel(null, 'portfolio_index')['archive.title']);
        $this->assertSame('Blog', DynamicTagResolver::resolve('post.title', DynamicTagResolver::payloadFromModel(null, 'blog_index')));
        $this->assertSame('Not found', DynamicTagResolver::payloadFromModel(null, 'not_found')['archive.title']);
        $this->assertSame('', DynamicTagResolver::payloadFromModel(null, 'unknown')['archive.title']);
    }

    public function test_context_from_path_resolves_archives_singles_and_unknown(): void
    {
        $project = Project::query()->create(['title' => 'P', 'slug' => 'registry-path', 'status' => 'published']);
        Project::query()->create(['title' => 'Draft', 'slug' => 'registry-draft', 'status' => 'draft']);
        $resolver = app(ChromeLayoutResolver::class);

        [$type, , $context] = $resolver->contextFromPath('/en/portfolio');
        $this->assertSame(['page', 'portfolio_index'], [$type, $context]);
        [$type, , $context] = $resolver->contextFromPath('/blog');
        $this->assertSame(['page', 'blog_index'], [$type, $context]);

        [$type, $record, $context] = $resolver->contextFromPath('/ar/work/registry-path');
        $this->assertSame(['project', 'project'], [$type, $context]);
        $this->assertSame($project->id, $record?->id);

        [$type, $record] = $resolver->contextFromPath('/work/registry-draft');
        $this->assertSame('project', $type);
        $this->assertNull($record);

        $this->assertSame(['homepage', null, 'not_found'], $resolver->contextFromPath('/nope/a/b'));
        $this->assertSame(['homepage', null, 'homepage'], $resolver->contextFromPath('/en'));
    }

    public function test_theme_conditions_use_registry_keys(): void
    {
        $template = new ThemeTemplate([
            'conditions' => ['relation' => 'or', 'rules' => [['group' => 'singular', 'key' => 'project', 'value' => 7]]],
        ]);
        $match = ThemeTemplateConditions::contextFromResolver('project', (new Project)->forceFill(['id' => 7]), 'project');
        $this->assertTrue(ThemeTemplateConditions::matches($template, $match));
        $this->assertSame(100, ThemeTemplateConditions::specificity($template, $match));

        $archive = new ThemeTemplate([
            'conditions' => ['rules' => [['group' => 'archive', 'key' => 'portfolio_index']]],
        ]);
        $this->assertTrue(ThemeTemplateConditions::matches($archive, ThemeTemplateConditions::contextFromResolver('page', null, 'portfolio_index')));
        $this->assertFalse(ThemeTemplateConditions::matches($archive, ThemeTemplateConditions::contextFromResolver('page', null, 'blog_index')));

        $bogus = new ThemeTemplate(['conditions' => ['rules' => [['group' => 'archive', 'key' => 'bogus_index']]]]);
        $this->assertFalse(ThemeTemplateConditions::matches($bogus, ThemeTemplateConditions::contextFromResolver('page', null, 'bogus_index')));

        $this->assertTrue(ThemeTemplateService::bodyAppliesForContext('services_index'));
        $this->assertFalse(ThemeTemplateService::bodyAppliesForContext('bogus_index'));
    }

    public function test_loop_items_are_localized_and_unknown_source_falls_back_to_blog(): void
    {
        $this->translatedProject();

        $items = LoopQueryService::items(['source' => 'projects', 'count' => 24], 'ar');
        $titles = array_column($items, 'title');
        $this->assertContains('متجر', $titles);
        $this->assertSame(['id', 'title', 'excerpt', 'url', 'date'], array_keys($items[0]));

        $blogIds = BlogPost::query()->where('status', 'published')->orderByDesc('id')->limit(3)->pluck('id')->all();
        $this->assertSame($blogIds, array_column(LoopQueryService::items(['source' => 'bogus', 'count' => 3]), 'id'));
    }

    public function test_registries_api_exposes_groups_kinds_and_content_types(): void
    {
        $response = $this->withHeaders($this->bearer($this->admin()))
            ->getJson('/api/appearance/registries')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'dynamic_tags' => [['id', 'group', 'label', 'kind']],
                    'dynamic_tag_groups' => [['id', 'label', 'parent', 'scope', 'content_type']],
                    'content_types' => [['key', 'label', 'plural_label', 'loop_source', 'archive']],
                ],
            ]);

        $this->assertSame(['page', 'project', 'blog_post', 'service'], array_column($response->json('data.content_types'), 'key'));
        $loop = collect($response->json('data.widgets'))->firstWhere('type', 'loop_grid');
        $source = collect($loop['settings_fields'])->firstWhere('key', 'source');
        $this->assertSame(['projects', 'blog', 'services'], array_column($source['options'], 'value'));
    }

    public function test_preview_records_endpoint(): void
    {
        $project = Project::query()->create(['title' => 'Preview me', 'slug' => 'registry-preview', 'status' => 'draft']);
        $headers = $this->bearer($this->admin());
        $rows = $this->withHeaders($headers)->getJson('/api/appearance/preview-records/project')->assertOk()->json('data');
        $this->assertSame(['id' => $project->id, 'title' => 'Preview me'], $rows[0]);

        $this->withHeaders($headers)->getJson('/api/appearance/preview-records/unknown')->assertNotFound();
    }

    public function test_preview_records_forbidden_for_editor(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('editor');

        $this->withHeaders($this->bearer($editor))->getJson('/api/appearance/preview-records/project')->assertForbidden();
    }
}
