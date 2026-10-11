<?php

namespace Tests\Feature;

use App\Models\AppMedia;
use App\Models\ChromeLayout;
use App\Models\Project;
use App\Models\User;
use App\Services\Appearance\ChromeLayoutService;
use App\Services\Appearance\DynamicTagResolver;
use App\Services\Appearance\PageLayoutDocument;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GalleryWidgetDynamicImagesTest extends TestCase
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

    private function image(string $name, string $alt = ''): AppMedia
    {
        return AppMedia::query()->create([
            'name' => $name,
            'file_name' => $name.'.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'size' => 1,
            'alt_text' => $alt,
            'collection_name' => 'library',
            'model_type' => 'library',
            'model_id' => 0,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);
    }

    /** @param list<int> $ids */
    private function project(array $ids): Project
    {
        return Project::query()->create([
            'title' => 'Shop', 'slug' => 'gallery-shop', 'status' => 'published', 'gallery_media_ids' => $ids,
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    private function sections(array $settings): array
    {
        return [[
            'id' => 'band_gal',
            'type' => PageLayoutDocument::TYPE_LAYOUT,
            'enabled' => true,
            'rows' => [[
                'id' => 'row_gal',
                'columns' => [[
                    'id' => 'col_gal',
                    'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                    'blocks' => [[
                        'id' => 'blk_gal',
                        'kind' => 'widget',
                        'type' => 'gallery',
                        'enabled' => true,
                        'settings' => ['images' => [['image' => 'https://example.test/manual.jpg', 'alt' => 'Manual']], ...$settings],
                    ]],
                ]],
            ]],
        ]];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>|null  $tagContext
     * @return array<string, mixed>
     */
    private function presented(array $settings, ?array $tagContext): array
    {
        $presented = PageLayoutDocument::presentPublicForPages($this->sections($settings), 'en', $tagContext);

        return $presented[0]['rows'][0]['columns'][0]['blocks'][0]['settings'];
    }

    public function test_project_gallery_fills_images_in_order(): void
    {
        $a = $this->image('a', 'First');
        $b = $this->image('b', 'Second');
        $context = DynamicTagResolver::payloadFromModel($this->project([$b->id, $a->id]), 'project');

        $settings = $this->presented(['dynamic_images' => true, 'images_source' => 'project.gallery'], $context);

        $this->assertTrue($settings['images_resolved']);
        $this->assertSame(['Second', 'First'], array_column($settings['images'], 'alt'));
        $this->assertStringContainsString('b.jpg', $settings['images'][0]['image']);
    }

    public function test_unknown_source_or_missing_record_gives_no_images(): void
    {
        $context = DynamicTagResolver::payloadFromModel($this->project([$this->image('a')->id]), 'project');

        $this->assertSame([], $this->presented(['dynamic_images' => true, 'images_source' => 'project.secrets'], $context)['images']);
        $this->assertSame([], $this->presented(['dynamic_images' => true, 'images_source' => 'project.gallery'], null)['images']);
    }

    public function test_manual_images_are_untouched_when_dynamic_is_off(): void
    {
        $context = DynamicTagResolver::payloadFromModel($this->project([$this->image('a')->id]), 'project');
        $settings = $this->presented(['dynamic_images' => false], $context);

        $this->assertSame('https://example.test/manual.jpg', $settings['images'][0]['image']);
        $this->assertArrayNotHasKey('images_resolved', $settings);
    }

    public function test_registries_list_gallery_sources(): void
    {
        $widgets = $this->withHeaders($this->bearer())->getJson('/api/appearance/registries')->assertOk()->json('data.widgets');
        $gallery = collect($widgets)->firstWhere('type', 'gallery');
        $fields = collect($gallery['settings_fields']);

        $this->assertContains('project.gallery', array_column($fields->firstWhere('key', 'images_source')['options'], 'value'));
        $this->assertSame('dynamic_images', $fields->firstWhere('key', 'images')['hide_if']);
    }

    public function test_preview_as_resolves_dynamic_gallery(): void
    {
        $project = $this->project([$this->image('a', 'Alt A')->id]);
        $layout = app(ChromeLayoutService::class)->create(ChromeLayout::KIND_SINGLE, ['name' => 'Project single']);

        $res = $this->withHeaders($this->bearer())->postJson("/api/appearance/singles/{$layout->id}/preview-as", [
            'content_type' => 'project',
            'record_id' => $project->id,
            'sections' => $this->sections(['dynamic_images' => true, 'images_source' => 'project.gallery']),
        ]);

        $res->assertOk();
        $this->assertSame('Alt A', $res->json('data.sections.0.rows.0.columns.0.blocks.0.settings.images.0.alt'));
    }
}
