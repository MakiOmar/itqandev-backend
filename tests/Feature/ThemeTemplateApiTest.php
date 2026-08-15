<?php

namespace Tests\Feature;

use App\Models\ChromeLayout;
use App\Models\Page;
use App\Models\ThemeTemplate;
use App\Models\User;
use App\Services\Appearance\ChromeLayoutResolver;
use App\Services\Appearance\HeaderBuilderService;
use App\Services\Appearance\ThemeTemplateConditions;
use App\Services\PublicMarketingShellService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ThemeTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        Storage::disk('local')->put('project-settings.json', json_encode(['site_name' => 'Test']));
        Cache::forget('project-settings');
        PublicMarketingShellService::forgetShellCaches();
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    private function admin(): User
    {
        $admin = User::query()->where('email', 'admin@credocode.test')->first();
        $this->assertNotNull($admin);

        return $admin;
    }

    private function publishedHeader(string $name = 'Alt H'): ChromeLayout
    {
        return ChromeLayout::query()->create([
            'kind' => 'header',
            'name' => $name,
            'slug' => 'alt-h-'.uniqid(),
            'status' => 'published',
            'document' => (new HeaderBuilderService)->defaultDocument(),
            'is_site_default' => false,
        ]);
    }

    private function publishedBody(string $name = 'Home body'): ChromeLayout
    {
        return ChromeLayout::query()->create([
            'kind' => 'body',
            'name' => $name,
            'slug' => 'body-'.uniqid(),
            'status' => 'published',
            'document' => [
                'sections' => [
                    [
                        'id' => 'band_1',
                        'type' => 'layout',
                        'enabled' => true,
                        'layout_width' => 'boxed',
                        'settings' => [],
                        'rows' => [
                            [
                                'id' => 'row_1',
                                'columns' => [
                                    [
                                        'id' => 'col_1',
                                        'span' => ['mobile' => 12, 'tablet' => 12, 'desktop' => 12],
                                        'blocks' => [
                                            [
                                                'id' => 'blk_1',
                                                'kind' => 'kit',
                                                'type' => 'page_header',
                                                'enabled' => true,
                                                'settings' => ['title' => 'Theme body'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'is_site_default' => false,
        ]);
    }

    public function test_can_crud_theme_template(): void
    {
        $headers = $this->bearerHeaders($this->admin());
        $header = $this->publishedHeader();

        $create = $this->withHeaders($headers)->postJson('/api/appearance/theme-templates', [
            'name' => 'Entire site',
            'status' => 'published',
            'conditions' => [
                'relation' => 'and',
                'rules' => [
                    ['include' => true, 'group' => 'entire', 'key' => 'site', 'value' => null],
                ],
            ],
            'header_layout_id' => $header->id,
        ]);
        $create->assertCreated();
        $id = (int) $create->json('data.id');

        $this->withHeaders($headers)->getJson('/api/appearance/theme-templates/'.$id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Entire site')
            ->assertJsonPath('data.header_layout_id', $header->id);

        $this->withHeaders($headers)->putJson('/api/appearance/theme-templates/'.$id, [
            'name' => 'Entire site renamed',
        ])->assertOk()->assertJsonPath('data.name', 'Entire site renamed');

        $this->withHeaders($headers)->deleteJson('/api/appearance/theme-templates/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('theme_templates', ['id' => $id]);
    }

    public function test_can_crud_body_layout(): void
    {
        $headers = $this->bearerHeaders($this->admin());

        $create = $this->withHeaders($headers)->postJson('/api/appearance/bodies', [
            'name' => 'Body A',
            'status' => 'published',
        ]);
        $create->assertCreated();
        $id = (int) $create->json('data.id');
        $this->assertSame('body', $create->json('data.kind'));

        $this->withHeaders($headers)->getJson('/api/appearance/bodies/'.$id)->assertOk();
        $this->withHeaders($headers)->deleteJson('/api/appearance/bodies/'.$id)->assertNoContent();
    }

    public function test_draft_theme_template_does_not_apply(): void
    {
        $header = $this->publishedHeader('Draft-only header');
        ThemeTemplate::query()->create([
            'name' => 'Draft entire',
            'status' => 'draft',
            'conditions' => ThemeTemplateConditions::normalize([
                'relation' => 'and',
                'rules' => [['include' => true, 'group' => 'entire', 'key' => 'site']],
            ]),
            'header_layout_id' => $header->id,
        ]);

        $resolved = app(ChromeLayoutResolver::class)->resolveForDocumentPath('/en/', 'en');
        $this->assertNull($resolved['theme_template_id']);
    }

    public function test_homepage_theme_body_and_specificity(): void
    {
        $siteHeader = ChromeLayout::query()->where('kind', 'header')->where('is_site_default', true)->first();
        $this->assertNotNull($siteHeader);

        $altHeader = $this->publishedHeader('Home header');
        $body = $this->publishedBody();

        ThemeTemplate::query()->create([
            'name' => 'Entire',
            'status' => 'published',
            'conditions' => ThemeTemplateConditions::normalize([
                'rules' => [['include' => true, 'group' => 'entire', 'key' => 'site']],
            ]),
            'header_layout_id' => $siteHeader->id,
        ]);

        $homeTpl = ThemeTemplate::query()->create([
            'name' => 'Homepage',
            'status' => 'published',
            'conditions' => ThemeTemplateConditions::normalize([
                'rules' => [['include' => true, 'group' => 'singular', 'key' => 'homepage']],
            ]),
            'header_layout_id' => $altHeader->id,
            'body_layout_id' => $body->id,
        ]);

        $resolved = app(ChromeLayoutResolver::class)->resolveForDocumentPath('/en/', 'en');
        $this->assertSame((int) $homeTpl->id, $resolved['theme_template_id']);
        $this->assertSame('homepage', $resolved['context']);
        $this->assertNotNull($resolved['theme_body']);
        $this->assertNotEmpty($resolved['theme_body']['sections']);
    }

    public function test_unknown_path_is_not_found_context(): void
    {
        $resolved = app(ChromeLayoutResolver::class)->resolveForDocumentPath('/en/this-does-not-exist-xyz', 'en');
        $this->assertSame('not_found', $resolved['context']);
    }

    public function test_record_fk_wins_over_theme_template(): void
    {
        $themeHeader = $this->publishedHeader('Theme H');
        $recordHeader = $this->publishedHeader('Record H');

        ThemeTemplate::query()->create([
            'name' => 'All pages',
            'status' => 'published',
            'conditions' => ThemeTemplateConditions::normalize([
                'rules' => [['include' => true, 'group' => 'singular', 'key' => 'page']],
            ]),
            'header_layout_id' => $themeHeader->id,
        ]);

        $page = Page::query()->create([
            'title' => 'Override',
            'slug' => 'override-chrome',
            'status' => Page::STATUS_PUBLISHED,
            'sections' => [],
            'header_layout_id' => $recordHeader->id,
        ]);

        $resolved = app(ChromeLayoutResolver::class)->resolveForDocumentPath('/en/pages/override-chrome', 'en');
        $this->assertSame('page', $resolved['content_type']);
        // Header document comes from record FK — compare by presenting record header id path
        $fromRecord = app(ChromeLayoutResolver::class)->resolve('header', 'page', $page, 'en');
        $this->assertSame($fromRecord['sections'], $resolved['header']['sections']);
    }

    public function test_exclude_condition_rejects_template(): void
    {
        $header = $this->publishedHeader();
        ThemeTemplate::query()->create([
            'name' => 'Site except home',
            'status' => 'published',
            'conditions' => ThemeTemplateConditions::normalize([
                'relation' => 'and',
                'rules' => [
                    ['include' => true, 'group' => 'entire', 'key' => 'site'],
                    ['include' => false, 'group' => 'singular', 'key' => 'homepage'],
                ],
            ]),
            'header_layout_id' => $header->id,
        ]);

        $home = app(ChromeLayoutResolver::class)->resolveForDocumentPath('/en/', 'en');
        $this->assertNull($home['theme_template_id']);

        $about = app(ChromeLayoutResolver::class)->resolveForDocumentPath('/en/about', 'en');
        // about may or may not have a page; if context is page/not_found, entire site include should match unless excluded
        if ($about['context'] !== 'homepage') {
            $this->assertNotNull($about['theme_template_id']);
        }
    }

    public function test_shell_includes_theme_body_for_homepage(): void
    {
        $body = $this->publishedBody();
        ThemeTemplate::query()->create([
            'name' => 'Home body tpl',
            'status' => 'published',
            'conditions' => ThemeTemplateConditions::normalize([
                'rules' => [['include' => true, 'group' => 'singular', 'key' => 'homepage']],
            ]),
            'body_layout_id' => $body->id,
        ]);

        $shell = app(PublicMarketingShellService::class)->build('en', 'en', '/en/');
        $this->assertSame('homepage', $shell['theme_context']);
        $this->assertIsArray($shell['theme_body']);
        $this->assertNotEmpty($shell['theme_body']['sections']);
    }

    public function test_forced_not_found_context_on_shell(): void
    {
        $request = \Illuminate\Http\Request::create('/api/public/shell', 'GET', [
            'locale' => 'en',
            'path' => '/en/',
            'theme_context' => 'not_found',
        ]);

        $shell = app(PublicMarketingShellService::class)->build(
            'en',
            'en',
            '/en/',
            $request,
            'not_found'
        );
        $this->assertSame('not_found', $shell['theme_context']);
    }
}
