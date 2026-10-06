<?php

namespace Tests\Feature;

use App\Models\ChromeLayout;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\ThemeTemplate;
use App\Services\Appearance\ThemeTemplateConditions;
use App\Services\PublicMarketingShellService;
use App\Support\ProjectSettingsStore;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PageThemeTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Cache::forget('project-settings');
        PublicMarketingShellService::forgetShellCaches();
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private function band(string $id, array $blocks): array
    {
        return [
            'id' => $id,
            'type' => 'layout',
            'enabled' => true,
            'layout_width' => 'boxed',
            'settings' => [],
            'rows' => [[
                'id' => $id.'_row',
                'columns' => [[
                    'id' => $id.'_col',
                    'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                    'blocks' => $blocks,
                ]],
            ]],
        ];
    }

    private function pageTemplate(array $rules): void
    {
        $single = ChromeLayout::query()->create([
            'kind' => 'single',
            'name' => 'Page template',
            'slug' => 'page-template-'.uniqid(),
            'status' => 'published',
            'document' => ['sections' => [
                $this->band('tpl_head', [
                    ['id' => 'crumbs', 'kind' => 'widget', 'type' => 'breadcrumb', 'enabled' => true, 'settings' => ['auto' => true]],
                    ['id' => 'title', 'kind' => 'widget', 'type' => 'post_title', 'enabled' => true, 'settings' => ['fallback' => '']],
                ]),
                $this->band('tpl_slot', [
                    ['id' => 'content', 'kind' => 'widget', 'type' => 'post_content', 'enabled' => true, 'settings' => []],
                ]),
            ]],
            'is_site_default' => false,
        ]);

        ThemeTemplate::query()->create([
            'name' => 'All pages',
            'document_type' => 'single',
            'status' => 'published',
            'conditions' => ThemeTemplateConditions::normalize(['relation' => 'and', 'rules' => $rules]),
            'body_layout_id' => $single->id,
        ]);
    }

    private function page(string $slug, string $title): Page
    {
        return Page::query()->create([
            'title' => $title,
            'slug' => $slug,
            'excerpt' => 'Intro',
            'status' => Page::STATUS_PUBLISHED,
            'sections' => [],
        ]);
    }

    /** @return array<string, mixed>|null */
    private function blockById(array $sections, string $id): ?array
    {
        foreach ($sections as $band) {
            foreach ($band['rows'] ?? [] as $row) {
                foreach ($row['columns'] ?? [] as $col) {
                    foreach ($col['blocks'] ?? [] as $block) {
                        if (($block['id'] ?? null) === $id) {
                            return $block;
                        }
                    }
                }
            }
        }

        return null;
    }

    public function test_page_template_body_is_served_with_the_page_title(): void
    {
        $this->pageTemplate([['include' => true, 'group' => 'singular', 'key' => 'page']]);
        $this->page('team', 'Our team');

        $response = $this->getJson('/api/public/shell?locale=en&path=/en/pages/team');

        $response->assertOk()->assertJsonPath('data.theme_context', 'page');
        $sections = $response->json('data.theme_body.sections');
        $this->assertIsArray($sections);
        $this->assertSame('Our team', $this->blockById($sections, 'title')['settings']['text'] ?? null);
        $this->assertNotNull($this->blockById($sections, 'content'), 'Post content placeholder reaches the client');
    }

    public function test_page_title_uses_the_requested_locale(): void
    {
        ProjectSettingsStore::save([
            'default_locale' => 'en',
            'site_languages' => [
                ['code' => 'en', 'label' => 'English', 'native_label' => 'English', 'rtl' => false],
                ['code' => 'ar', 'label' => 'Arabic', 'native_label' => 'Arabic', 'rtl' => true],
            ],
        ]);
        $this->pageTemplate([['include' => true, 'group' => 'singular', 'key' => 'page']]);
        $page = $this->page('team', 'Our team');
        PageTranslation::query()->create(['page_id' => $page->id, 'locale' => 'ar', 'title' => 'فريقنا', 'excerpt' => '']);

        $response = $this->getJson('/api/public/shell?path=/ar/pages/team', ['X-Content-Locale' => 'ar']);

        $response->assertOk();
        $sections = $response->json('data.theme_body.sections');
        $this->assertSame('فريقنا', $this->blockById($sections, 'title')['settings']['text'] ?? null);
    }

    public function test_client_navigation_data_path_resolves_as_the_page(): void
    {
        $this->pageTemplate([['include' => true, 'group' => 'singular', 'key' => 'page']]);
        $this->page('team', 'Our team');

        $response = $this->getJson('/api/public/shell?locale=en&path=/en/pages/team/q-data.json');

        $response->assertOk()->assertJsonPath('data.theme_context', 'page');
        $this->assertSame(
            'Our team',
            $this->blockById($response->json('data.theme_body.sections'), 'title')['settings']['text'] ?? null
        );
    }

    public function test_excluded_page_gets_no_template_body(): void
    {
        $this->pageTemplate([
            ['include' => true, 'group' => 'singular', 'key' => 'page'],
            ['include' => false, 'group' => 'singular', 'key' => 'page', 'value' => $this->page('landing', 'Landing')->id],
        ]);
        $this->page('team', 'Our team');

        $this->getJson('/api/public/shell?locale=en&path=/en/pages/landing')
            ->assertOk()
            ->assertJsonPath('data.theme_body', null);
        $this->assertNotNull(
            $this->getJson('/api/public/shell?locale=en&path=/en/pages/team')->json('data.theme_body')
        );
    }
}
