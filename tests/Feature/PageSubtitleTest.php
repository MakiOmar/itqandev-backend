<?php

namespace Tests\Feature;

use App\Models\ChromeLayout;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\ThemeTemplate;
use App\Models\User;
use App\Services\Appearance\ThemeTemplateConditions;
use App\Services\PublicMarketingShellService;
use App\Support\ProjectSettingsStore;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PageSubtitleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::forget('project-settings');
        PublicMarketingShellService::forgetShellCaches();
    }

    private function enableArabic(): void
    {
        ProjectSettingsStore::save([
            'default_locale' => 'en',
            'site_languages' => [
                ['code' => 'en', 'label' => 'English', 'native_label' => 'English', 'rtl' => false],
                ['code' => 'ar', 'label' => 'Arabic', 'native_label' => 'Arabic', 'rtl' => true],
            ],
        ]);
    }

    private function actingAsEditor(): void
    {
        Permission::findOrCreate('manage pages');
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo('manage pages');
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);
    }

    /** @return array<string, mixed> */
    private function headingBand(string $blockId, string $text): array
    {
        return [
            'id' => 'band_'.$blockId,
            'type' => 'layout',
            'enabled' => true,
            'layout_width' => 'boxed',
            'settings' => [],
            'rows' => [[
                'id' => 'row_'.$blockId,
                'columns' => [[
                    'id' => 'col_'.$blockId,
                    'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                    'blocks' => [[
                        'id' => $blockId,
                        'kind' => 'widget',
                        'type' => 'heading',
                        'enabled' => true,
                        'settings' => ['text' => $text, 'tag' => 'h2'],
                    ]],
                ]],
            ]],
        ];
    }

    private function headingText(array $sections, string $blockId): ?string
    {
        foreach ($sections as $band) {
            foreach ($band['rows'] ?? [] as $row) {
                foreach ($row['columns'] ?? [] as $col) {
                    foreach ($col['blocks'] ?? [] as $block) {
                        if (($block['id'] ?? null) === $blockId) {
                            return $block['settings']['text'] ?? null;
                        }
                    }
                }
            }
        }

        return null;
    }

    public function test_editor_saves_subtitle_and_public_api_returns_it(): void
    {
        $this->actingAsEditor();

        $id = $this->postJson('/api/v1/pages', [
            'title' => 'Team',
            'slug' => 'team',
            'subtitle' => 'The people behind the work',
            'status' => Page::STATUS_PUBLISHED,
        ])->assertCreated()->assertJsonPath('subtitle', 'The people behind the work')->json('id');

        $this->getJson('/api/public/pages/team')
            ->assertOk()
            ->assertJsonPath('subtitle', 'The people behind the work');
        $this->assertSame(
            'The people behind the work',
            collect($this->getJson('/api/public/pages')->json())->firstWhere('id', $id)['subtitle'] ?? null
        );
    }

    public function test_subtitle_is_optional_and_validated(): void
    {
        $this->actingAsEditor();

        $this->postJson('/api/v1/pages', ['title' => 'Plain', 'slug' => 'plain'])
            ->assertCreated()
            ->assertJsonPath('subtitle', null);
        $this->postJson('/api/v1/pages', ['title' => 'Long', 'slug' => 'long', 'subtitle' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subtitle');
    }

    public function test_translation_save_without_subtitle_key_keeps_the_stored_subtitle(): void
    {
        $this->enableArabic();
        $this->actingAsEditor();
        $page = Page::query()->create(['title' => 'Team', 'slug' => 'team', 'subtitle' => 'Main', 'status' => Page::STATUS_PUBLISHED, 'sections' => []]);
        PageTranslation::query()->create(['page_id' => $page->id, 'locale' => 'ar', 'title' => 'فريقنا', 'subtitle' => 'العنوان الفرعي']);

        $this->putJson('/api/v1/pages/'.$page->id, [
            'title' => 'Team',
            'translations' => [['locale' => 'ar', 'title' => 'فريقنا', 'excerpt' => '']],
        ])->assertOk();

        $this->assertSame('Main', $page->fresh()->subtitle);
        $this->assertSame('العنوان الفرعي', $page->translations()->where('locale', 'ar')->value('subtitle'));
    }

    public function test_translated_page_shows_only_its_own_subtitle(): void
    {
        $this->enableArabic();
        $page = Page::query()->create(['title' => 'Team', 'slug' => 'team', 'subtitle' => 'Main', 'status' => Page::STATUS_PUBLISHED, 'sections' => []]);
        PageTranslation::query()->create(['page_id' => $page->id, 'locale' => 'ar', 'title' => 'فريقنا']);

        $this->getJson('/api/public/pages/team', ['X-Content-Locale' => 'ar'])
            ->assertOk()
            ->assertJsonPath('title', 'فريقنا')
            ->assertJsonPath('subtitle', null);
    }

    public function test_post_tags_resolve_inside_page_content(): void
    {
        Page::query()->create([
            'title' => 'Team',
            'slug' => 'team',
            'subtitle' => 'The people',
            'status' => Page::STATUS_PUBLISHED,
            'sections' => [$this->headingBand('hero', '{{post.title}} — {{post.subtitle}}')],
        ]);

        $sections = $this->getJson('/api/public/pages/team')->assertOk()->json('sections');

        $this->assertSame('Team — The people', $this->headingText($sections, 'hero'));
    }

    public function test_subtitle_tag_resolves_in_a_page_template(): void
    {
        $layout = ChromeLayout::query()->create([
            'kind' => 'single',
            'name' => 'Page template',
            'slug' => 'page-template-subtitle',
            'status' => 'published',
            'document' => ['sections' => [$this->headingBand('sub', '{{post.subtitle}}')]],
            'is_site_default' => false,
        ]);
        ThemeTemplate::query()->create([
            'name' => 'All pages',
            'document_type' => 'single',
            'status' => 'published',
            'conditions' => ThemeTemplateConditions::normalize([
                'relation' => 'and',
                'rules' => [['include' => true, 'group' => 'singular', 'key' => 'page']],
            ]),
            'body_layout_id' => $layout->id,
        ]);
        Page::query()->create(['title' => 'Team', 'slug' => 'team', 'subtitle' => 'The people', 'status' => Page::STATUS_PUBLISHED, 'sections' => []]);

        $sections = $this->getJson('/api/public/shell?locale=en&path=/en/pages/team')
            ->assertOk()
            ->json('data.theme_body.sections');

        $this->assertSame('The people', $this->headingText($sections, 'sub'));
    }

    public function test_subtitle_tag_is_listed_in_the_registry(): void
    {
        $this->assertContains('post.subtitle', array_column(\App\Services\Appearance\DynamicTagRegistry::all(), 'id'));
    }
}
