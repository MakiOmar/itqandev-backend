<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\ChromeLayout;
use App\Models\Project;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Appearance\ChromeLayoutService;
use App\Services\Appearance\DynamicTagResolver;
use App\Services\Appearance\PageLayoutDocument;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ListWidgetDynamicItemsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::forget('project-settings');
    }

    /** @return array<string, string> */
    private function bearer(): array
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->firstOrFail();

        return ['Authorization' => 'Bearer '.$admin->createToken('test')->plainTextToken];
    }

    private function project(): Project
    {
        $project = Project::query()->create(['title' => 'Shop', 'slug' => 'list-shop', 'status' => 'published']);
        $category = Category::query()->create(['name' => 'E-commerce', 'slug' => 'list-ecommerce']);
        CategoryTranslation::query()->create(['category_id' => $category->id, 'locale' => 'ar', 'name' => 'متاجر']);
        $project->categories()->attach($category->id);
        Testimonial::query()->create(['project_id' => $project->id, 'client_name' => 'Hidden', 'content' => 'Unapproved', 'approved' => false]);
        Testimonial::query()->create(['project_id' => $project->id, 'client_name' => 'Sara', 'content' => 'Great work', 'approved' => true]);

        return $project->fresh();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    private function sections(array $settings): array
    {
        return [[
            'id' => 'band_list',
            'type' => PageLayoutDocument::TYPE_LAYOUT,
            'enabled' => true,
            'rows' => [[
                'id' => 'row_list',
                'columns' => [[
                    'id' => 'col_list',
                    'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                    'blocks' => [[
                        'id' => 'blk_list',
                        'kind' => 'widget',
                        'type' => 'list',
                        'enabled' => true,
                        'settings' => ['style' => 'ul', 'items' => [['text' => 'Manual']], ...$settings],
                    ]],
                ]],
            ]],
        ]];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>|null  $tagContext
     * @return mixed
     */
    private function presentedItems(array $settings, ?array $tagContext, string $locale = 'en'): mixed
    {
        $presented = PageLayoutDocument::presentPublicForPages($this->sections($settings), $locale, $tagContext);

        return $presented[0]['rows'][0]['columns'][0]['blocks'][0]['settings']['items'] ?? null;
    }

    public function test_categories_resolve_localised_with_links(): void
    {
        $context = DynamicTagResolver::payloadFromModel($this->project(), 'project', 'ar');

        $this->assertSame(
            [['text' => 'متاجر', 'url' => '/portfolio?category_slug=list-ecommerce']],
            $this->presentedItems(['dynamic_items' => true, 'items_source' => 'project.categories:name', 'link_items' => true], $context, 'ar'),
        );
    }

    public function test_testimonials_keep_only_approved_without_links(): void
    {
        $context = DynamicTagResolver::payloadFromModel($this->project(), 'project');

        $this->assertSame(
            [['text' => 'Great work', 'url' => '']],
            $this->presentedItems(['dynamic_items' => true, 'items_source' => 'project.testimonials:quote'], $context),
        );
    }

    public function test_link_items_off_drops_urls(): void
    {
        $context = DynamicTagResolver::payloadFromModel($this->project(), 'project');

        $this->assertSame(
            [['text' => 'E-commerce', 'url' => '']],
            $this->presentedItems(['dynamic_items' => true, 'items_source' => 'project.categories:name', 'link_items' => false], $context),
        );
    }

    public function test_unknown_source_or_missing_record_gives_no_items(): void
    {
        $context = DynamicTagResolver::payloadFromModel($this->project(), 'project');

        $this->assertSame([], $this->presentedItems(['dynamic_items' => true, 'items_source' => 'project.secrets:name'], $context));
        $this->assertSame([], $this->presentedItems(['dynamic_items' => true, 'items_source' => 'project.categories:name'], null));
    }

    public function test_unset_source_uses_the_first_option(): void
    {
        $context = DynamicTagResolver::payloadFromModel($this->project(), 'project');

        $this->assertSame(
            [['text' => 'E-commerce', 'url' => '/portfolio?category_slug=list-ecommerce']],
            $this->presentedItems(['dynamic_items' => true, 'items_source' => ''], $context),
        );
    }

    public function test_manual_items_are_untouched_when_dynamic_is_off(): void
    {
        $context = DynamicTagResolver::payloadFromModel($this->project(), 'project');

        $this->assertSame([['text' => 'Manual']], $this->presentedItems(['dynamic_items' => false, 'items_source' => 'project.categories:name'], $context));
    }

    public function test_registries_list_relation_sources(): void
    {
        $widgets = $this->withHeaders($this->bearer())->getJson('/api/appearance/registries')->assertOk()->json('data.widgets');
        $list = collect($widgets)->firstWhere('type', 'list');
        $values = array_column(collect($list['settings_fields'])->firstWhere('key', 'items_source')['options'], 'value');

        $this->assertContains('project.categories:name', $values);
        $this->assertContains('project.skills:name', $values);
        $this->assertContains('project.testimonials:quote', $values);
        $this->assertNotContains('project.categories:image', $values, 'only text fields are list sources');
        $this->assertSame('dynamic_items', collect($list['settings_fields'])->firstWhere('key', 'items')['hide_if']);
    }

    public function test_preview_as_resolves_dynamic_list_in_unsaved_sections(): void
    {
        $project = $this->project();
        $layout = app(ChromeLayoutService::class)->create(ChromeLayout::KIND_SINGLE, ['name' => 'Project single']);

        $res = $this->withHeaders($this->bearer())->postJson("/api/appearance/singles/{$layout->id}/preview-as", [
            'content_type' => 'project',
            'record_id' => $project->id,
            'locale' => 'ar',
            'sections' => $this->sections(['dynamic_items' => true, 'items_source' => 'project.categories:name']),
        ]);

        $res->assertOk();
        $this->assertSame('متاجر', $res->json('data.sections.0.rows.0.columns.0.blocks.0.settings.items.0.text'));
        $this->assertTrue($res->json('data.sections.0.rows.0.columns.0.blocks.0.settings.items_resolved'));
    }
}
